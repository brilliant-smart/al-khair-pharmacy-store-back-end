<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\ControlledSubstanceLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrescriptionController extends Controller
{
    /**
     * Get all prescriptions
     */
    public function index(Request $request)
    {
        $query = Prescription::with(['items.product', 'dispensedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_name')) {
            $query->where('patient_name', 'like', '%' . $request->patient_name . '%');
        }

        if ($request->filled('prescription_number')) {
            $query->where('prescription_number', 'like', '%' . $request->prescription_number . '%');
        }

        $prescriptions = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($prescriptions);
    }

    /**
     * Create prescription
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:255',
            'patient_phone' => 'nullable|string|max:20',
            'patient_address' => 'nullable|string',
            'patient_dob' => 'nullable|date',
            'doctor_name' => 'required|string|max:255',
            'doctor_license' => 'nullable|string|max:255',
            'hospital' => 'nullable|string|max:255',
            'prescription_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:prescription_date',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // 5MB
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.prescribed_quantity' => 'required|integer|min:1',
            'items.*.dosage' => 'nullable|string',
            'items.*.frequency' => 'nullable|string',
            'items.*.duration_days' => 'nullable|integer|min:1',
            'items.*.instructions' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Handle image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('prescriptions', 'public');
                $validated['image_url'] = $path;
            }

            $validated['prescription_number'] = Prescription::generatePrescriptionNumber();
            $validated['status'] = 'pending';

            $prescription = Prescription::create($validated);

            // Create prescription items
            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                $prescription->items()->create([
                    'product_id' => $itemData['product_id'],
                    'prescribed_quantity' => $itemData['prescribed_quantity'],
                    'dosage' => $itemData['dosage'] ?? null,
                    'frequency' => $itemData['frequency'] ?? null,
                    'duration_days' => $itemData['duration_days'] ?? null,
                    'instructions' => $itemData['instructions'] ?? null,
                    'is_controlled_substance' => $product->is_controlled_substance ?? false,
                ]);
            }

            // Audit log
            AuditLog::log('created', $prescription, null, $prescription->toArray(), 'Prescription created');

            return response()->json([
                'message' => 'Prescription created successfully',
                'prescription' => $prescription->load('items.product'),
            ], 201);
        });
    }

    /**
     * Get prescription details
     */
    public function show(Prescription $prescription)
    {
        return response()->json($prescription->load(['items.product', 'dispensedBy']));
    }

    /**
     * Dispense prescription
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        if ($prescription->status === 'dispensed') {
            return response()->json(['message' => 'Prescription already dispensed'], 422);
        }

        if ($prescription->isExpired()) {
            return response()->json(['message' => 'Prescription has expired'], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.prescription_item_id' => 'required|exists:prescription_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'patient_id_type' => 'nullable|string', // For controlled substances
            'patient_id_number' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($prescription, $validated) {
            $fullyDispensed = true;

            foreach ($validated['items'] as $itemData) {
                $prescriptionItem = PrescriptionItem::findOrFail($itemData['prescription_item_id']);
                
                if ($prescriptionItem->prescription_id !== $prescription->id) {
                    throw new \Exception('Invalid prescription item');
                }

                $quantityToDispense = min($itemData['quantity'], $prescriptionItem->remaining_quantity);
                
                if ($quantityToDispense <= 0) {
                    continue;
                }

                // Check stock availability
                $product = $prescriptionItem->product;
                if ($product->stock_quantity < $quantityToDispense) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                // Update prescription item
                $prescriptionItem->dispensed_quantity += $quantityToDispense;
                $prescriptionItem->save();

                // Deduct stock
                $product->decrement('stock_quantity', $quantityToDispense);

                // Log controlled substance if applicable
                if ($product->is_controlled_substance) {
                    ControlledSubstanceLog::create([
                        'product_id' => $product->id,
                        'transaction_type' => 'dispensed',
                        'quantity' => $quantityToDispense,
                        'balance_before' => $product->stock_quantity + $quantityToDispense,
                        'balance_after' => $product->stock_quantity,
                        'prescription_id' => $prescription->id,
                        'patient_name' => $prescription->patient_name,
                        'patient_id_type' => $validated['patient_id_type'] ?? null,
                        'patient_id_number' => $validated['patient_id_number'] ?? null,
                        'dispensed_by' => auth()->id(),
                        'reference_number' => $prescription->prescription_number,
                    ]);
                }

                if (!$prescriptionItem->isFullyDispensed()) {
                    $fullyDispensed = false;
                }
            }

            // Update prescription status
            if ($fullyDispensed && $prescription->isFullyDispensed()) {
                $prescription->update([
                    'status' => 'dispensed',
                    'dispensed_by' => auth()->id(),
                    'dispensed_at' => now(),
                ]);
            } else {
                $prescription->update([
                    'status' => 'partially_dispensed',
                    'dispensed_by' => auth()->id(),
                    'dispensed_at' => $prescription->dispensed_at ?? now(),
                ]);
            }

            // Audit log
            AuditLog::log('dispensed', $prescription, null, null, 'Prescription dispensed');

            return response()->json([
                'message' => 'Prescription dispensed successfully',
                'prescription' => $prescription->fresh()->load('items.product'),
            ]);
        });
    }

    /**
     * Cancel prescription
     */
    public function cancel(Prescription $prescription)
    {
        if (in_array($prescription->status, ['dispensed', 'cancelled'])) {
            return response()->json(['message' => 'Cannot cancel this prescription'], 422);
        }

        $prescription->update(['status' => 'cancelled']);

        AuditLog::log('cancelled', $prescription, null, null, 'Prescription cancelled');

        return response()->json(['message' => 'Prescription cancelled successfully']);
    }
}

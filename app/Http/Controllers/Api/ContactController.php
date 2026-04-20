<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Send contact form email
     */
    public function sendContactEmail(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        try {
            // Send email to info@alkhairstore.com
            Mail::raw(
                "New Contact Form Submission\n\n" .
                "From: {$validated['name']}\n" .
                "Email: {$validated['email']}\n" .
                "Phone: {$validated['phone']}\n" .
                "Subject: {$validated['subject']}\n\n" .
                "Message:\n{$validated['message']}\n\n" .
                "---\n" .
                "Sent from Al-Khair Pharmacy & Store Website",
                function ($message) use ($validated) {
                    $message->to(env('CONTACT_EMAIL', 'info@alkhairstore.com'))
                        ->subject('Contact Form: ' . $validated['subject'])
                        ->replyTo($validated['email'], $validated['name']);
                }
            );

            // Log the contact form submission
            Log::info('Contact form submitted', [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
            ]);

            return response()->json([
                'message' => 'Thank you for contacting us! We will get back to you soon.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Contact form error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Sorry, there was an error sending your message. Please try again or contact us directly.',
            ], 500);
        }
    }
}

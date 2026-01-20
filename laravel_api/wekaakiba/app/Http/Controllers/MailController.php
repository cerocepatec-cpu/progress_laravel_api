<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MailController extends Controller
{
    
       public function sendFiles(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'emails' => 'required|array',
            'emails.*' => 'email',
            'subject' => 'required|string',
            'message' => 'required|string',
            'files' => 'nullable|array',
            'files.*' => 'string',
        ]);

        $enterprise = $this->getEse($validated['user_id']);

        foreach ($validated['emails'] as $email) {
            try {
                Mail::raw($validated['message'], function ($message) use ($email, $validated, $enterprise) {
                    $message->to($email)
                            ->subject($validated['subject']);
                    try {
                        $message->from($enterprise['mail'], $enterprise['name']);
                    } catch (\Exception $e) {
                        Log::warning('Impossible d’utiliser from(), fallback sur replyTo(): ' . $e->getMessage());
                        $message->from(config('mail.from.address'), config('mail.from.name'))
                                ->replyTo($enterprise['mail'], $enterprise['name']);
                    }

                    if (!empty($validated['files'])) {
                        foreach ($validated['files'] as $filePath) {
                            if (file_exists($filePath)) {
                                $message->attach($filePath);
                            }
                        }
                    }
                });
            } catch (\Exception $e) {
                Log::error("Échec d'envoi à {$email}: " . $e->getMessage());
            }
        }

        return response()->json(['status' => 'Emails envoyés avec succès']);
    }

    public function sendTestEmail(Request $request)
    {
        $to = $request->query('to', 'kilimbanyifabrice@gmail.com');

        try {
            Mail::raw('Ceci est un mail de test envoyé depuis Laravel avec Gmail SMTP.', function ($message) use ($to) {
                $message->to($to)
                        ->subject('✅ Test Laravel Gmail SMTP');
            });

            return response()->json([
                'status' => 'success',
                'message' => "Mail de test envoyé à $to"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Erreur lors de l'envoi : " . $e->getMessage()
            ], 500);
        }
    }
}

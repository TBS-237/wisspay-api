<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function transfer(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|string', // wisspay_id
            'amount' => 'required|numeric|min:100',
            'pin' => 'required|string|digits:4'
        ]);

        $sender = $request->user();
        
        // 1. Verify PIN
        if (!Hash::check($request->pin, $sender->pin_code)) {
            return response()->json(['message' => 'Code PIN incorrect'], 403);
        }

        // 2. Find Recipient
        $recipient = User::where('wisspay_id', $request->recipient_id)->first();
        if (!$recipient) {
            return response()->json(['message' => 'Destinataire non trouvé'], 404);
        }

        if ($recipient->id === $sender->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous envoyer d\'argent à vous-même'], 400);
        }

        $senderWallet = $sender->wallet;
        $recipientWallet = $recipient->wallet;

        // 3. Check Balance
        if ($senderWallet->balance < $request->amount) {
            return response()->json(['message' => 'Solde insuffisant'], 400);
        }

        // 4. Atomic Transaction
        try {
            DB::beginTransaction();

            $reference = 'WSP-' . Str::upper(Str::random(10));

            // Deduct from sender
            $senderWallet->decrement('balance', $request->amount);
            Transaction::create([
                'wallet_id' => $senderWallet->id,
                'amount' => -$request->amount,
                'type' => 'TRANSFER_OUT',
                'status' => 'COMPLETED',
                'reference' => $reference,
                'counterparty_id' => $recipient->id,
                'counterparty_name' => $recipient->name,
                'description' => "Envoi vers {$recipient->name}"
            ]);

            // Add to recipient
            $recipientWallet->increment('balance', $request->amount);
            Transaction::create([
                'wallet_id' => $recipientWallet->id,
                'amount' => $request->amount,
                'type' => 'TRANSFER_IN',
                'status' => 'COMPLETED',
                'reference' => $reference,
                'counterparty_id' => $sender->id,
                'counterparty_name' => $sender->name,
                'description' => "Reçu de {$sender->name}"
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transfert effectué avec succès',
                'reference' => $reference,
                'new_balance' => $senderWallet->balance
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Une erreur est survenue lors du transfert'], 500);
        }
    }
}

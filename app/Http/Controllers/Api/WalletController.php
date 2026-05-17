<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function getBalance(Request $request)
    {
        return response()->json([
            'balance' => $request->user()->wallet->balance,
            'currency' => $request->user()->wallet->currency,
        ]);
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'recipient_id' => 'required|string', // wisspay_id
            'pin' => 'required|string|digits:4',
            'description' => 'nullable|string',
        ]);

        $sender = $request->user();

        // 1. PIN Verification
        if (!Hash::check($request->pin, $sender->pin_code)) {
            return response()->json(['message' => 'Code PIN incorrect'], 403);
        }

        // 2. Find Recipient
        $receiver = User::where('wisspay_id', $request->recipient_id)->first();
        if (!$receiver) {
            return response()->json(['message' => 'Destinataire non trouvé'], 404);
        }

        if ($sender->id === $receiver->id) {
            return response()->json(['message' => 'Transfert vers vous-même impossible'], 422);
        }

        if ($sender->wallet->balance < $request->amount) {
            return response()->json(['message' => 'Solde insuffisant'], 422);
        }

        return DB::transaction(function () use ($sender, $receiver, $request) {
            $amount = $request->amount;
            $reference = 'WSP-' . strtoupper(Str::random(10));

            // Update balances
            $sender->wallet->decrement('balance', $amount);
            $receiver->wallet->increment('balance', $amount);

            // Log transactions
            Transaction::create([
                'wallet_id' => $sender->wallet->id,
                'amount' => -$amount,
                'type' => 'TRANSFER_OUT',
                'status' => 'COMPLETED',
                'reference' => $reference,
                'counterparty_id' => $receiver->id,
                'counterparty_name' => $receiver->name,
                'description' => $request->description ?: "Envoi vers {$receiver->name}",
            ]);

            Transaction::create([
                'wallet_id' => $receiver->wallet->id,
                'amount' => $amount,
                'type' => 'TRANSFER_IN',
                'status' => 'COMPLETED',
                'reference' => $reference,
                'counterparty_id' => $sender->id,
                'counterparty_name' => $sender->name,
                'description' => $request->description ?: "Reçu de {$sender->name}",
            ]);

            return response()->json([
                'message' => 'Transfert réussi',
                'reference' => $reference,
                'new_balance' => $sender->wallet->fresh()->balance
            ]);
        });
    }

    public function getTransactions(Request $request)
    {
        $transactions = $request->user()->wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return response()->json($transactions);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $country = $request->country ?? 'CM';
        $count = User::count() + 1;
        $date = now()->format('ym');
        $wissPayId = "WSP-{$country}-{$date}-{$count}";

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'country' => $country,
            'wisspay_id' => $wissPayId,
            'password' => Hash::make($request->password),
            'role' => 'CLIENT',
            'kyc_status' => 'PENDING',
            'kyc_data' => $request->kyc_data, // Expecting JSON from frontend
        ]);

        // Create wallet
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'currency' => 'XAF',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request['email'])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été banni. Contactez l\'administrateur.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('wallet'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->hasFile('avatar_file')) {
            $file = $request->file('avatar_file');
            if ($file->isValid()) {
                if ($user->avatar && str_contains($user->avatar, '/uploads/avatars/')) {
                    $oldPath = public_path(parse_url($user->avatar, PHP_URL_PATH));
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $folder = public_path('uploads/avatars');
                if (!file_exists($folder)) {
                    mkdir($folder, 0755, true);
                }

                $extension = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'avatar_' . time() . '_' . Str::random(8) . '.' . strtolower($extension);
                $file->move($folder, $filename);
                $user->avatar = asset('uploads/avatars/' . $filename);
            }
        } elseif ($request->exists('avatar')) {
            $avatar = $request->avatar;
                if ($avatar === '' && $user->avatar && str_contains($user->avatar, '/uploads/avatars/')) {
                $oldPath = public_path(parse_url($user->avatar, PHP_URL_PATH));
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
                $user->avatar = null;
            } elseif ($avatar !== '') {
                $user->avatar = $avatar;
            }
        }

        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function updatePin(Request $request)
    {
        $request->validate(['pin' => 'required|digits:4']);
        $user = $request->user();
        $user->pin_code = Hash::make($request->pin);
        $user->save();

        return response()->json(['message' => 'PIN code updated successfully']);
    }

    public function updateKyc(Request $request)
    {
        $user = $request->user();
        $user->kyc_status = 'PENDING';
        $user->save();

        return response()->json(['message' => 'KYC status updated to PENDING']);
    }
}

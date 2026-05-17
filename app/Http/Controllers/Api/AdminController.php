<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Banner;

class AdminController extends Controller
{
    public function getStats()
    {
        $last7Days = collect(range(6, 0))->map(function($i) {
            $date = today()->subDays($i)->format('Y-m-d');
            return [
                'date' => $date,
                'count' => User::whereDate('created_at', $date)->count(),
                'revenue' => Transaction::whereDate('created_at', $date)->sum('amount')
            ];
        });

        return response()->json([
            'total_users' => User::count(),
            'total_balance' => Wallet::sum('balance'),
            'total_transactions' => Transaction::count(),
            'daily_transactions' => Transaction::whereDate('created_at', today())->count(),
            'pending_kyc' => User::where('kyc_status', 'PENDING')->count(),
            'total_banners' => Banner::count(),
            'chart_data' => $last7Days
        ]);
    }

    public function getUsers()
    {
        return response()->json(User::with('wallet')->orderBy('created_at', 'desc')->paginate(20));
    }

    public function getSettings()
    {
        $defaults = [
            'site_active' => true,
            'allow_registrations' => true,
            'banner_rotation' => true,
            'default_currency' => 'XAF',
        ];

        $settings = Setting::all()->pluck('value', 'key')->map(function ($value) {
            $decoded = json_decode($value, true);
            return $decoded === null ? $value : $decoded;
        })->toArray();

        return response()->json(array_merge($defaults, $settings));
    }

    public function saveSettings(Request $request)
    {
        $allowedKeys = ['site_active', 'allow_registrations', 'banner_rotation', 'default_currency'];

        foreach ($allowedKeys as $key) {
            if ($request->exists($key)) {
                $value = $request->$key;
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => is_bool($value) || is_numeric($value) ? json_encode($value) : $value]
                );
            }
        }

        if ($request->hasFile('avatar_file') || $request->exists('avatar')) {
            $user = Auth::user();
            if ($user) {
                if ($request->hasFile('avatar_file')) {
                    $file = $request->file('avatar_file');
                    if ($file->isValid()) {
                        $folder = public_path('uploads/avatars');
                        if (!file_exists($folder)) {
                            mkdir($folder, 0755, true);
                        }

                        $extension = $file->getClientOriginalExtension() ?: 'jpg';
                        $filename = 'avatar_' . time() . '_' . Str::random(8) . '.' . strtolower($extension);
                        $file->move($folder, $filename);
                        $user->avatar = asset('uploads/avatars/' . $filename);
                    }
                } elseif ($request->input('avatar') === '') {
                    $user->avatar = null;
                }
                $user->save();
            }
        }

        return response()->json(['message' => 'Settings saved successfully']);
    }

    public function updateUserRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->role = $request->role; // CLIENT or ADMIN
        $user->save();

        return response()->json(['message' => 'Role updated successfully']);
    }

    public function getPendingKyc()
    {
        return response()->json(User::where('kyc_status', 'PENDING')->orderBy('created_at', 'desc')->get());
    }

    public function getBanners()
    {
        return response()->json(Banner::orderBy('order')->get());
    }

    public function createBanner(Request $request)
    {
        $data = $request->only(['title', 'subtitle', 'order', 'image_url']);

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            if ($file->isValid()) {
                $folder = public_path('uploads/banners');
                if (!file_exists($folder)) {
                    mkdir($folder, 0755, true);
                }

                $extension = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . strtolower($extension);
                $file->move($folder, $filename);
                $data['image_url'] = asset('uploads/banners/' . $filename);
            }
        } elseif (!empty($data['image_url']) && preg_match('/^data:image\/([a-zA-Z]+);base64,/', $data['image_url'], $matches)) {
            $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
            $base64 = preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $data['image_url']);
            $decoded = base64_decode($base64);

            if ($decoded === false) {
                return response()->json(['message' => 'Image invalide'], 422);
            }

            $folder = public_path('uploads/banners');
            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }

            $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . $extension;
            $filePath = $folder . '/' . $filename;
            file_put_contents($filePath, $decoded);
            $data['image_url'] = asset('uploads/banners/' . $filename);
        }

        $banner = Banner::create($data);
        return response()->json($banner);
    }

    public function updateBanner(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $request->only(['title', 'subtitle', 'order', 'image_url']);

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            if ($file->isValid()) {
                $folder = public_path('uploads/banners');
                if (!file_exists($folder)) {
                    mkdir($folder, 0755, true);
                }

                $extension = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . strtolower($extension);
                $file->move($folder, $filename);
                $data['image_url'] = asset('uploads/banners/' . $filename);
            }
        } elseif (!empty($data['image_url']) && preg_match('/^data:image\/([a-zA-Z]+);base64,/', $data['image_url'], $matches)) {
            $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
            $base64 = preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $data['image_url']);
            $decoded = base64_decode($base64);

            if ($decoded === false) {
                return response()->json(['message' => 'Image invalide'], 422);
            }

            $folder = public_path('uploads/banners');
            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }

            $filename = 'banner_' . time() . '_' . Str::random(8) . '.' . $extension;
            $filePath = $folder . '/' . $filename;
            file_put_contents($filePath, $decoded);
            $data['image_url'] = asset('uploads/banners/' . $filename);
        }

        $banner->update(array_filter($data, fn($value) => $value !== null));
        return response()->json($banner);
    }

    public function deleteBanner($id)
    {
        Banner::destroy($id);
        return response()->json(['message' => 'Banner deleted']);
    }

    public function validateKyc(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->kyc_status = $request->status; // VALIDATED or REJECTED
        $user->save();

        return response()->json(['message' => "KYC updated to {$request->status}"]);
    }

    public function getTransactions()
    {
        return response()->json(Transaction::with('wallet.user')->orderBy('created_at', 'desc')->paginate(50));
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json(['message' => 'User status updated', 'is_active' => $user->is_active]);
    }
}

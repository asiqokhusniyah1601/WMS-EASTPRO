<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\GsmSimcard;
use App\Models\DeviceTransaction;
use Illuminate\Support\Facades\DB;

class JwtHelper
{
    private static $secret = 'DLMS_SECURE_SECRET_KEY_12345';

    public static function encode(array $payload): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        list($header, $payload, $signature) = $parts;
        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
        $validBase64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        if ($validBase64UrlSignature !== $signature) {
            return null;
        }
        return json_decode(base64_decode($payload), true);
    }
}

class ApiController extends Controller
{
    private function validateBearerToken(Request $request): ?array
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = substr($authHeader, 7);
        return JwtHelper::decode($token);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Simple auth check for API client
        if ($request->email === 'test@example.com' && $request->password === 'password') {
            $token = JwtHelper::encode([
                'email' => $request->email,
                'role' => 'API_User',
                'exp' => time() + 3600 // 1 hour expiration
            ]);

            return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized credentials'
        ], 401);
    }

    public function search(Request $request)
    {
        $user = $this->validateBearerToken($request);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired JWT token'], 401);
        }

        $q = $request->query('q', '');
        if (empty($q)) {
            return response()->json(['status' => 'error', 'message' => 'Query parameter q is required'], 400);
        }

        $devices = Device::with('gsmSimcard')
            ->where('serial_number', 'like', "%$q%")
            ->orWhere('imei', 'like', "%$q%")
            ->orWhere('vehicle_plate', 'like', "%$q%")
            ->get();

        return response()->json([
            'status' => 'success',
            'results' => $devices
        ]);
    }

    public function syncInstallation(Request $request)
    {
        $user = $this->validateBearerToken($request);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired JWT token'], 401);
        }

        $request->validate([
            'device_sn' => 'required|exists:devices,serial_number',
            'msisdn' => 'required|exists:gsm_simcards,msisdn',
            'vehicle_plate' => 'required|string'
        ]);

        $result = DB::transaction(function() use ($request) {
            $device = Device::where('serial_number', $request->device_sn)->firstOrFail();
            $simcard = GsmSimcard::where('msisdn', $request->msisdn)->firstOrFail();

            // Link Device and SIM Card
            $device->update([
                'status' => 'INSTALLED',
                'gsm_simcard_id' => $simcard->id,
                'vehicle_plate' => $request->vehicle_plate,
                'current_holder' => 'Vehicle Plate: ' . $request->vehicle_plate,
            ]);

            $simcard->update([
                'status' => 'INSTALLED'
            ]);

            // Add Audit Trail log
            DeviceTransaction::create([
                'device_id' => $device->id,
                'device_sn' => $device->serial_number,
                'action' => 'INSTALLED',
                'from_location' => $device->warehouse_code,
                'to_location' => 'Installation on ' . $request->vehicle_plate,
                'operator' => 'API Synchronizer',
                'scanned_by' => 'API-Client',
                'via_web' => false
            ]);

            return $device->load('gsmSimcard');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Installation synced and device linked successfully.',
            'device' => $result
        ]);
    }
}

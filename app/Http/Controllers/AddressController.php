<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Models\UserAddress;
use Exception;

class AddressController extends Controller
{
    /*----------------------------------------
      🔐 Token Validation (Reusable)
    ----------------------------------------*/
    private function validateTokenAndEmail(Request $request)
    {
        try {
            $token = $request->header('Authorization');

            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Authorization token missing'], 401);
            }

            $token = str_replace('Bearer ', '', $token);

            $decoded = json_decode(Crypt::decryptString($token), true);

            if (!isset($decoded['email'])) {
                return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
            }

            $email = $request->query('email');

            if (!$email) {
                return response()->json(['success' => false, 'message' => 'Email parameter is required'], 400);
            }

            if ($decoded['email'] !== $email) {
                return response()->json(['success' => false, 'message' => 'Email mismatch with token'], 403);
            }

            $user = AuthUser::where('email', $email)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            return $user;

        } catch (Exception $e) {
            Log::error("Token Validation Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Token verification failed'
            ], 401);
        }
    }

    /*----------------------------------------
      🟩 Add Address
    ----------------------------------------*/
    public function addAddress(Request $request)
    {
        try {
            // Validate Token
            $user = $this->validateTokenAndEmail($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $request->validate([
                'name' => 'required|string',
                'phone' => 'required|string',
                'building_name' => 'required|string',
                'address_1' => 'required|string',
                'city' => 'required|string',
                'district' => 'required|string',
                'state' => 'required|string',
                'pincode' => 'required|string',
                'landmark' => 'required|string',
                'address_type' => 'required|in:home,work'
            ]);

            $address = UserAddress::create([
                'auth_user_id' => $user->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'building_name' => $request->building_name,
                'address_1' => $request->address_1,
                'address_2' => $request->address_2,
                'city' => $request->city,
                'district' => $request->district,
                'state' => $request->state,
                'pincode' => $request->pincode,
                'landmark' => $request->landmark,
                'address_type' => $request->address_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address added successfully',
                'data' => $address
            ]);

        } catch (Exception $e) {
            Log::error("Add Address Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to add address'
            ], 500);
        }
    }

    /*----------------------------------------
      🟨 Get Addresses
    ----------------------------------------*/
    public function getAddresses(Request $request)
    {
        try {
            $user = $this->validateTokenAndEmail($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $addresses = UserAddress::where('auth_user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Addresses fetched successfully',
                'data' => $addresses
            ]);

        } catch (Exception $e) {
            Log::error("Get Address Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Could not fetch addresses'
            ], 500);
        }
    }

    /*----------------------------------------
      🟦 Update Address
    ----------------------------------------*/
    public function updateAddress(Request $request)
    {
        try {
            $user = $this->validateTokenAndEmail($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $request->validate([
                'id' => 'required|integer|exists:user_addresses,id',
                'address' => 'required|array',
                'address.name' => 'required|string',
                'address.phone' => 'required|string',
                'address.building_name' => 'required|string',
                'address.address_1' => 'required|string',
                'address.city' => 'required|string',
                'address.district' => 'required|string',
                'address.state' => 'required|string',
                'address.pincode' => 'required|string',
                'address.landmark' => 'required|string',
                'address.address_type' => 'required|in:home,work'
            ]);

            $address = UserAddress::where('id', $request->id)
                ->where('auth_user_id', $user->id)
                ->first();

            if (!$address) {
                return response()->json(['success' => false, 'message' => 'Address not found'], 404);
            }

            $address->update($request->address);

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => $address
            ]);

        } catch (Exception $e) {
            Log::error("Update Address Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to update address'
            ], 500);
        }
    }

    /*----------------------------------------
      🟥 Delete Address
    ----------------------------------------*/
    public function deleteAddress(Request $request)
    {
        try {
            $user = $this->validateTokenAndEmail($request);
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;

            $request->validate([
                'id' => 'required|integer|exists:user_addresses,id'
            ]);

            $address = UserAddress::where('id', $request->id)
                ->where('auth_user_id', $user->id)
                ->first();

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Delete Address Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to delete address'
            ], 500);
        }
    }

    public function getAddressByUserId(Request $request)
    {
        try {
            $user_id = $request->query('user_id');
            $token = $request->header('Authorization');

            if (!$user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id parameter is required'
                ], 400);
            }

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization token missing'
                ], 401);
            }

            $token = str_replace('Bearer ', '', $token);

            // Token decode
            try {
                $decoded = json_decode(Crypt::decryptString($token), true);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
            }

            // Token & user_id must match the same user
            if ($decoded['unique_id'] !== $user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID mismatch with token user'
                ], 403);
            }

            // Find actual user record
            $user = AuthUser::where('unique_id', $user_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Fetch addresses
            $addresses = UserAddress::where('auth_user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Addresses fetched successfully',
                'data' => $addresses
            ]);

        } catch (Exception $e) {
            \Log::error("GetAddressByUserId Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Unable to fetch address'
            ], 500);
        }
    }

    

}

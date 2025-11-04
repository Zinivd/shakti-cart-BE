<?php

namespace App\Http\Controllers;

use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Models\UserAddress;
use Carbon\Carbon;

class AddressController extends Controller
{
    public function addAddress(Request $request)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Authorization token missing']);
        }

        $token = str_replace('Bearer ', '', $token);

        // Decrypt token to extract user details
        try {
            $decoded = json_decode(Crypt::decryptString($token), true);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token']);
        }

        $user = AuthUser::where('unique_id', $decoded['unique_id'])->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }

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
    }

    public function getAddresses(Request $request)
{
    $email = $request->query('email');
    $token = $request->header('Authorization');

    if (!$email) {
        return response()->json(['success' => false, 'message' => 'Email parameter is required']);
    }

    if (!$token) {
        return response()->json(['success' => false, 'message' => 'Authorization token missing']);
    }

    $token = str_replace('Bearer ', '', $token);

    try {
        $decoded = json_decode(Crypt::decryptString($token), true);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Invalid or expired token']);
    }

    // Token email and param email must match
    if ($decoded['email'] !== $email) {
        return response()->json(['success' => false, 'message' => 'Email mismatch with token']);
    }

    $user = AuthUser::where('email', $email)->first();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User not found']);
    }

    $addresses = UserAddress::where('auth_user_id', $user->id)->get();

    return response()->json([
        'success' => true,
        'message' => 'Addresses fetched successfully',
        'data' => $addresses
    ]);
}

public function updateAddress(Request $request)
{
    $token = $request->header('Authorization');
    $email = $request->query('email'); // email in params

    if (!$token) {
        return response()->json(['success' => false, 'message' => 'Authorization token missing']);
    }

    if (!$email) {
        return response()->json(['success' => false, 'message' => 'Email parameter is required']);
    }

    $token = str_replace('Bearer ', '', $token);

    try {
        $decoded = json_decode(Crypt::decryptString($token), true);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Invalid or expired token']);
    }

    if ($decoded['email'] !== $email) {
        return response()->json(['success' => false, 'message' => 'Email mismatch with token']);
    }

    $user = AuthUser::where('email', $email)->first();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User not found']);
    }

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
        return response()->json(['success' => false, 'message' => 'Address not found or unauthorized']);
    }

    $address->update([
        'name' => $request->address['name'],
        'phone' => $request->address['phone'],
        'building_name' => $request->address['building_name'],
        'address_1' => $request->address['address_1'],
        'address_2' => $request->address['address_2'] ?? null,
        'city' => $request->address['city'],
        'district' => $request->address['district'],
        'state' => $request->address['state'],
        'pincode' => $request->address['pincode'],
        'landmark' => $request->address['landmark'],
        'address_type' => $request->address['address_type']
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Address updated successfully',
        'data' => $address
    ]);
}
}

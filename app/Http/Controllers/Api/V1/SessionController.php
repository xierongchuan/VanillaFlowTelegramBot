<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SessionController extends Controller
{
    public function store(Request $req)
    {
        $req->validate([
            'login'    => 'required|min:4|max:255',
            'password' => 'required|min:6|max:255',
        ]);

        $user = User::where('login', $req->login)->first();
        if (! $user || ! Hash::check($req->password, $user->password)) {
            return response()->json(['message' => 'Неверные данные'], 401);
        }

        $token = $user->createToken('user-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Сессия завершена']);
    }
}

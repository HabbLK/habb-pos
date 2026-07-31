<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegisterSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegisterSessionController extends Controller
{
    /** The cashier's current open shift for this business type, if any. */
    public function current(Request $request)
    {
        $session = RegisterSession::where('user_id', $request->user()->id)
            ->where('business_type', $request->query('business_type'))
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return response()->json(['data' => $session]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'business_type' => ['required', 'string'],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = RegisterSession::where('user_id', $request->user()->id)
            ->where('business_type', $data['business_type'])
            ->where('status', 'open')
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'business_type' => 'You already have an open register for this business type.',
            ]);
        }

        $session = RegisterSession::create([
            'user_id' => $request->user()->id,
            'business_type' => $data['business_type'],
            'opening_float' => $data['opening_float'],
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function close(Request $request, RegisterSession $registerSession)
    {
        $data = $request->validate([
            'closing_count' => ['required', 'numeric', 'min:0'],
        ]);

        if ($registerSession->status !== 'open') {
            throw ValidationException::withMessages(['status' => 'This register is already closed.']);
        }

        $cashSales = $registerSession->orders()
            ->where('status', 'completed')
            ->where('payment_method', 'Cash')
            ->sum('total');

        $expected = (float) $registerSession->opening_float + (float) $cashSales;

        $registerSession->update([
            'expected_cash' => $expected,
            'closing_count' => $data['closing_count'],
            'difference' => round($data['closing_count'] - $expected, 2),
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json(['data' => $registerSession]);
    }
}

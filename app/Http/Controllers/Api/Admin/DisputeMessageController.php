<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\DisputeMessage;
use Illuminate\Http\Request;

class DisputeMessageController extends Controller
{
    public function index(Trade $trade)
    {
        return $trade->disputeMessages()->with('user')->orderBy('created_at', 'asc')->get();
    }

    public function store(Request $request, Trade $trade)
    {
        $data = $request->validate([
            'message'    => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('dispute-attachments', 'public');
        }

        $msg = DisputeMessage::create([
            'trade_id'        => $trade->id,
            'user_id'         => auth()->id(),
            'message'         => $data['message'],
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json($msg->load('user'), 201);
    }
}

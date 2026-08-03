<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reply to Ticket #{{ $ticket->id }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #374151; line-height: 1.6; margin: 0; padding: 20px; background-color: #f3f4f6; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 32px; background-color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 24px; }
        .header h2 { color: #1e3a8a; margin: 0; font-size: 20px; font-weight: 700; }
        .content { margin-bottom: 24px; font-size: 15px; }
        .reply-box { background-color: #f0f9ff; border-left: 4px solid #2563eb; padding: 18px; margin: 20px 0; border-radius: 6px; white-space: pre-wrap; font-size: 15px; color: #1e293b; }
        .history-section { margin-top: 32px; border-top: 2px solid #f1f5f9; padding-top: 24px; }
        .history-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 16px; }
        .history-item { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin-bottom: 12px; }
        .history-header { font-size: 12px; color: #64748b; margin-bottom: 6px; font-weight: 600; display: flex; justify-content: space-between; }
        .history-body { font-size: 14px; color: #334155; white-space: pre-wrap; }
        .signature { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 20px; color: #4b5563; font-weight: 700; font-size: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Support Ticket Update (#{{ $ticket->id }})</h2>
        </div>
        
        <div class="content">
            <p>Hi {{ $customerFirstName }},</p>
            <p>Thank you for reaching out to our support team. We have an update regarding your ticket: <strong>{{ $ticket->subject }}</strong>.</p>
            
            <p><strong>Latest Update:</strong></p>
            <div class="reply-box">{{ $reply->body }}</div>

            <p>If you have any further questions or additional details to share, please reply directly to this email.</p>
        </div>

        {{-- Ticket Conversation History --}}
        @php
            $previousReplies = $ticket->replies->where('id', '!=', $reply->id)->sortByDesc('created_at');
        @endphp

        @if($previousReplies->isNotEmpty())
            <div class="history-section">
                <div class="history-title">Previous Conversation History</div>
                @foreach($previousReplies as $prevReply)
                    <div class="history-item">
                        <div class="history-header">
                            <span>
                                <strong>{{ $prevReply->user ? $prevReply->user->name : ($prevReply->sender_type ? $prevReply->sender_type->label() : 'Support') }}</strong>
                            </span>
                            <span>{{ $prevReply->created_at->format('M d, Y g:i A') }}</span>
                        </div>
                        <div class="history-body">{{ $prevReply->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="signature">
            <p>{{ $signature }}</p>
        </div>
    </div>
</body>
</html>

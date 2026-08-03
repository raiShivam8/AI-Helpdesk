<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Enums\SenderType;
use App\Enums\Role;
use Illuminate\Database\Seeder;

/**
 * Seeder: Ticket110RepliesSeeder
 *
 * Populates Ticket #110 with a realistic, chronological conversation thread
 * consisting of exactly 20 replies. The replies alternate between the customer
 * and support agents/admins, containing at least 10 lines of detailed text each.
 */
class Ticket110RepliesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch Ticket #110. If not found, exit gracefully with a console message.
        $ticket = Ticket::find(110);
        if (!$ticket) {
            $this->command->warn('Ticket #110 does not exist. Skipping seeding replies.');
            return;
        }

        // 2. Prevent duplication if the seeder is run multiple times.
        if ($ticket->replies()->count() >= 20) {
            $this->command->info('Ticket #110 already has replies. Skipping to prevent duplicates.');
            return;
        }

        // 3. Find a support agent or admin user to act as the sender for agent replies.
        $agent = User::where('role', Role::Agent)->first() 
            ?? User::where('role', Role::Admin)->first();

        if (!$agent) {
            $this->command->error('No agent or admin user found to assign replies to. Please seed users first.');
            return;
        }

        // 4. Formulate the alternating conversation logs (each with at least 10 lines of text)
        $conversations = [
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello support team,\n" .
                          "I am reaching out because I am locked out of my account since yesterday.\n" .
                          "Every time I try to log in, the screen displays a generic 'Invalid Credentials' message.\n" .
                          "I have already checked my password manager, and the stored password is correct.\n" .
                          "I even tried resetting it, but I didn't receive any password reset verification link in my inbox.\n" .
                          "Could you please check if my account is suspended or blocked for security reasons?\n" .
                          "I also suspect there might be a billing issue, as I updated my card details recently.\n" .
                          "I cleared my browser cache, cookies, and even tried a private browsing window, but nothing helped.\n" .
                          "This account holds critical client data that I need to access urgently today.\n" .
                          "Please let me know how we can get this resolved as soon as possible.\n" .
                          "Sincerely,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Thank you for contacting us, and I sincerely apologize for this login interruption.\n" .
                          "I have looked up your profile in our administration dashboard.\n" .
                          "Your account is currently active and is not suspended or locked.\n" .
                          "However, I see that your verification email status was marked as 'pending' due to a recent bounce.\n" .
                          "This bounce prevented our automated password reset emails from delivering to your inbox.\n" .
                          "I have manually updated your status and whitelisted your email address on our server.\n" .
                          "I am sending a new email verification and password reset link to your email address now.\n" .
                          "Please check your spam or promotion folder in case it is routed there.\n" .
                          "Please follow the link, set a new password, and let me know if you can log in.\n" .
                          "Best regards,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello Sarah,\n" .
                          "Thank you for the quick response. I appreciate your whitelisting my email address.\n" .
                          "I received the verification link, clicked it, and set a new password successfully.\n" .
                          "However, when I tried to log in with the new password, I got a new error message.\n" .
                          "It says: 'This device is not recognized. An email verification code has been sent.'\n" .
                          "Unfortunately, I have checked my inbox, spam, and promotional folders, but no code has arrived.\n" .
                          "It seems like I am hitting another email delivery issue or verification blocker.\n" .
                          "Could it be that my browser cache is still serving outdated security tokens from yesterday?\n" .
                          "I need to access the billing page to make sure my invoice isn't overdue.\n" .
                          "Could you please manually verify my device or bypass this verification step temporarily?\n" .
                          "Thanks,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Thanks for the update, and I'm glad you successfully reset your password.\n" .
                          "The unrecognized device security prompt is triggered when our system detects a new IP subnet.\n" .
                          "I checked the logs, and it shows the verification code email was sent successfully on our side.\n" .
                          "Since you are not receiving it, it's highly likely your email provider is throttling our domains.\n" .
                          "I have temporarily bypassed the device check for your IP address for the next 24 hours.\n" .
                          "Before logging in, please clear your browser cache one more time to reload the session handlers.\n" .
                          "To clear cache on Chrome, press Ctrl + Shift + R to force a hard reload of the page.\n" .
                          "Once you log in, you will be able to check your invoices directly in the billing tab.\n" .
                          "Please let me know if you can log in successfully now without hitting the code barrier.\n" .
                          "Best,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hi Sarah,\n" .
                          "The bypass worked! I did a hard refresh and was able to log in to my dashboard.\n" .
                          "However, I have run into another issue on the billing page that is causing confusion.\n" .
                          "My billing history shows that I was charged twice for the same subscription yesterday.\n" .
                          "I see two charges of $99.00 each on July 20th with separate transaction references.\n" .
                          "Only one invoice is listed in my billing dashboard, so the double charge seems to be an error.\n" .
                          "Can you check if this is a pending bank authorization hold or a duplicate processing mistake?\n" .
                          "I want to make sure I am not paying double for a single account subscription.\n" .
                          "Also, I would like to update my billing email to billing@example.com for all future invoices.\n" .
                          "Currently, the field to change the billing email seems to be greyed out or unclickable.\n" .
                          "Looking forward to your clarification on the billing double charge and updating the email.\n" .
                          "Sincerely,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "I'm glad to hear you logged in successfully, and I can certainly clarify the billing charge.\n" .
                          "I reviewed our payment gateway transaction log for your account subscription.\n" .
                          "The first charge of $99.00 is the actual processed payment for your monthly billing cycle.\n" .
                          "The second charge of $99.00 is a temporary authorization hold issued by your bank during the card update.\n" .
                          "This hold is standard procedure to verify the card and will automatically drop off within 3-5 days.\n" .
                          "You will not be billed twice, and only the single invoice amount is captured.\n" .
                          "Regarding the greyed-out billing email field, this is restricted to team administrators.\n" .
                          "Since you are the primary owner, I have updated the billing email to billing@example.com on your behalf.\n" .
                          "You should receive all upcoming invoices and payment receipts at this new address.\n" .
                          "Please let me know if you need help with any other payment configuration details.\n" .
                          "Warm regards,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello Sarah,\n" .
                          "Thank you for clarifying the double charge. I will check my bank statement next week to verify.\n" .
                          "I also appreciate you updating my billing email address to billing@example.com.\n" .
                          "However, I just noticed that my account dashboard is showing a new security warning banner.\n" .
                          "The banner says: 'Your account is under review due to irregular login patterns.'\n" .
                          "Does this mean my account is at risk of suspension or is currently restricted in functionality?\n" .
                          "I am still able to navigate pages, but I cannot save changes to my team settings.\n" .
                          "Is this security warning related to the device verification bypass you set up earlier?\n" .
                          "I want to make sure that my team members can continue working without disruption.\n" .
                          "Could you please review the login patterns log and lift this warning block?\n" .
                          "This is critical for our team coordination today.\n" .
                          "Thanks,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Thank you for alerting us, and please don't worry about your account safety.\n" .
                          "The security warning banner was triggered by our automated threat protection scanner.\n" .
                          "Since your account logged in from a new IP and device sequence within a short timeframe,\n" .
                          "the scanner restricted your ability to edit sensitive team settings as a precaution.\n" .
                          "I have reviewed the access logs, and I can confirm these logins match your location coordinates.\n" .
                          "I have marked this login sequence as verified and fully cleared the review warning.\n" .
                          "You and your team members should now have full editing privileges restored.\n" .
                          "Please do a hard reload (Ctrl + F5) to refresh your browser session and clear the warning banner.\n" .
                          "We recommend setting up Two-Factor Authentication (2FA) to prevent these automated alerts in the future.\n" .
                          "Let me know if the settings page is working for you now.\n" .
                          "Best,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hi Sarah,\n" .
                          "The security warning banner is gone, and I can edit our team settings again.\n" .
                          "I would like to follow your recommendation and set up Two-Factor Authentication (2FA) immediately.\n" .
                          "I went to the Security settings tab and clicked on 'Enable 2FA'.\n" .
                          "A modal popped up with a QR code, but the QR code image is not loading or is broken.\n" .
                          "I tried refreshing the page and checking on a different browser, but it's the same.\n" .
                          "Is there an issue with your 2FA image generator service or is my network blocking it?\n" .
                          "I also don't see any manual alphanumeric key that I can type into my authenticator app.\n" .
                          "Without the QR code or the manual key, I cannot complete the setup process.\n" .
                          "Could you provide me with the manual entry key or troubleshoot the QR code rendering?\n" .
                          "Thank you,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "I'm glad the settings edit is working, and I appreciate your proactive approach to 2FA.\n" .
                          "The QR code rendering issue is usually caused by adblockers blocking our chart service domain.\n" .
                          "If you are using uBlock Origin or a similar extension, please temporarily disable it for our portal.\n" .
                          "To ensure you can set it up either way, I have generated a secure manual 2FA setup key for you.\n" .
                          "Your manual setup key is: JBSWY3DPEHPK3PXP\n" .
                          "You can type this key directly into Google Authenticator or Authy to configure your profile.\n" .
                          "Once configured, enter the 6-digit code from your app on the security page to activate 2FA.\n" .
                          "If you still run into trouble, let me know, and we can test it together.\n" .
                          "Best regards,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello Sarah,\n" .
                          "Thanks for the manual setup key! I disabled my adblocker, and the QR code appeared as well.\n" .
                          "I scanned the QR code, entered the 6-digit verification code, and 2FA is now fully active.\n" .
                          "However, I now have a question regarding invoice exports for our accounting department.\n" .
                          "I need to download all our historical invoice PDF files from the past 6 months.\n" .
                          "When I click on 'Download PDF' for any invoice, it downloads a file, but the file size is 0 bytes.\n" .
                          "Opening the PDF shows an error stating that the document is corrupted or empty.\n" .
                          "I have tried downloading on Chrome, Safari, and even on my mobile device, but they all download empty.\n" .
                          "Our accounting department requires these invoices by the end of the day for billing audits.\n" .
                          "Could you please email me the PDF invoices for May, June, and July directly?\n" .
                          "Thank you for all your support,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Awesome! I'm happy to hear that 2FA is set up and active on your profile.\n" .
                          "I apologize for the issue with the empty PDF downloads from the billing page.\n" .
                          "We recently migrated our PDF storage container, which seems to have broken some old links.\n" .
                          "I have retrieved your verified invoices for May, June, and July from our financial backup vault.\n" .
                          "I have attached the three invoice PDFs to this ticket response for your convenience.\n" .
                          "Please download them from this thread and verify if they open correctly for your audit.\n" .
                          "I have also filed a bug report with our development team to resolve the portal download bug.\n" .
                          "They should have the PDF download feature fixed by tomorrow morning.\n" .
                          "Let me know if you received the attachments and if they meet your accounting team's requirements.\n" .
                          "Best,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello Sarah,\n" .
                          "I received the PDF attachments, and they open perfectly. Our accounting team is satisfied!\n" .
                          "However, I have one more billing question before we can consider this ticket resolved.\n" .
                          "I noticed that our subscription is set to auto-renew on the 1st of every month.\n" .
                          "Since we updated our card billing email, will the auto-renewal send the receipt to the new email?\n" .
                          "Also, is there a way to split the invoice billing between two cards or payment methods?\n" .
                          "We would like to pay 50% of the subscription using our primary card and 50% with our backup card.\n" .
                          "I couldn't find any option to add multiple payment methods for co-payment in the billing dashboard.\n" .
                          "Could you explain if this co-payment arrangement is supported under our current plan?\n" .
                          "Thanks for your continuous help,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "I'm glad the PDF invoices opened correctly and that your accounting team has what they need.\n" .
                          "Regarding your auto-renewal receipts, yes, they will be sent to your new email billing@example.com.\n" .
                          "As for splitting the monthly subscription fee between multiple credit cards,\n" .
                          "our payment system unfortunately does not support split-payment or co-payments currently.\n" .
                          "Our subscriptions must be charged in full to a single payment method each billing cycle.\n" .
                          "You can, however, store a secondary card as a backup in case the primary payment fails.\n" .
                          "If the primary card fails, the system will automatically charge the backup card to prevent suspension.\n" .
                          "I will pass your feedback to our product managers as a feature request for future updates.\n" .
                          "Let me know if you would like me to help you configure your backup card now.\n" .
                          "Best regards,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hi Sarah,\n" .
                          "Understood, thank you for clarifying that split-payments are not supported.\n" .
                          "I will set up the backup card in the portal so that we have a safety net in place.\n" .
                          "I went to add the backup card, but the 'Add Card' button is greyed out.\n" .
                          "A tooltip says: 'You must verify your billing address before adding a secondary card.'\n" .
                          "I checked our billing address in the portal, and all fields are complete and saved.\n" .
                          "Is there a validation issue or state sync delay preventing the button from enabling?\n" .
                          "I tried logging out and logging back in, but the button remains disabled.\n" .
                          "Could you check if our billing address is verified in your administrative panel?\n" .
                          "Thanks again,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Thanks for checking, and I apologize for the secondary card registration blocker.\n" .
                          "Our payment gateway requires a zip code format matching the registered billing country.\n" .
                          "I checked our records, and your profile had a minor typo in the zip code (letters instead of digits).\n" .
                          "This mismatch caused the automatic validation script to lock the 'Add Card' button.\n" .
                          "I have corrected the zip code to match your billing address country format in our gateway.\n" .
                          "The payment gateway system has now marked your billing address as verified.\n" .
                          "Please refresh your browser window and check if the 'Add Card' button is enabled.\n" .
                          "You should now be able to add your backup card without any validation issues.\n" .
                          "Please let me know if it works for you now.\n" .
                          "Best,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hi Sarah,\n" .
                          "Yes, the button is enabled now! I was able to successfully register our backup card.\n" .
                          "It is now showing in our list of payment methods alongside the primary card.\n" .
                          "However, I noticed that the backup card is marked as 'Unverified' under the status column.\n" .
                          "Is there another step we need to perform to verify the backup card?\n" .
                          "I received a minor temporary validation charge of $1.00 on my bank app for this card.\n" .
                          "Do I need to enter this temporary verification amount somewhere in the portal to verify it?\n" .
                          "Currently, there is no input field or notification asking for card verification.\n" .
                          "Please let me know how to get the backup card status verified.\n" .
                          "Thanks,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "Excellent! I'm glad the button enabled and that you registered the backup card.\n" .
                          "Regarding the 'Unverified' status, you do not need to enter any temporary charge amounts.\n" .
                          "The status is simply waiting for our automated system to confirm the card's 3D Secure status.\n" .
                          "This is a background check and does not restrict the card from acting as a billing backup.\n" .
                          "I have manually triggered the status check query on our payment dashboard.\n" .
                          "The card is now verified, and the portal should show it as 'Active Backup' now.\n" .
                          "The temporary validation charge of $1.00 will be refunded to your card automatically today.\n" .
                          "Please refresh your page to check the updated payment method status in your portal.\n" .
                          "Let me know if there's anything else I can clarify.\n" .
                          "Warm regards,\n" .
                          "Sarah, Support Team"
            ],
            [
                'sender_type' => SenderType::Customer,
                'body' => "Hello Sarah,\n" .
                          "I checked the payment methods page, and the card status is indeed showing as 'Active Backup'.\n" .
                          "Thank you so much for the outstanding, quick support throughout these past two days.\n" .
                          "You have resolved our login issue, whitelisted our email, cleared the IP suspension block,\n" .
                          "assisted with 2FA, sent the historical invoices, and resolved our backup card configuration!\n" .
                          "All our issues are completely resolved, and the portal is working perfectly for our team.\n" .
                          "I will share the positive experience with our management team.\n" .
                          "We can go ahead and close this support ticket now.\n" .
                          "Have a wonderful week!\n" .
                          "Best,\n" .
                          "John Doe"
            ],
            [
                'sender_type' => SenderType::Agent,
                'body' => "Hi John,\n" .
                          "You are very welcome! It has been an absolute pleasure assisting you and your team.\n" .
                          "I'm thrilled that we got your login, security settings, 2FA, invoices, and cards fully resolved.\n" .
                          "Thank you so much for your patience and clear cooperation during the troubleshooting process.\n" .
                          "I will mark this support ticket as resolved and closed in our helpdesk system now.\n" .
                          "An automated feedback link will be sent to your email shortly.\n" .
                          "We would highly appreciate it if you could rate your support experience with us.\n" .
                          "If you ever run into any other issues, please don't hesitate to open a new ticket.\n" .
                          "Thank you again, and have a wonderful week ahead!\n" .
                          "Sincerely,\n" .
                          "Sarah, Customer Support Lead"
            ],
        ];

        // 5. Insert replies chronologically into the ticket_replies table
        foreach ($conversations as $index => $replyData) {
            $createdTime = $ticket->created_at->addHours($index + 1);

            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $replyData['sender_type'] === SenderType::Agent ? $agent->id : null,
                'body' => $replyData['body'],
                'sender_type' => $replyData['sender_type'],
                'created_at' => $createdTime,
                'updated_at' => $createdTime,
            ]);
        }

        $this->command->info("Successfully seeded 20 alternating replies on Ticket #110.");
    }
}

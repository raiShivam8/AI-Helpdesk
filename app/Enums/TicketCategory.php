<?php

namespace App\Enums;

enum TicketCategory: string
{
    case GeneralQuestion = 'general question';
    case TechnicalQuestion = 'technical question';
    case RefundRequest = 'refund request';

    case Billing = 'billing';
    case TechnicalSupport = 'technical support';
    case BugReport = 'bug report';
    case FeatureRequest = 'feature request';
    case Account = 'account';
    case GeneralInquiry = 'general inquiry';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::GeneralQuestion => 'General Question',
            self::TechnicalQuestion => 'Technical Question',
            self::RefundRequest => 'Refund Request',
            self::Billing => 'Billing',
            self::TechnicalSupport => 'Technical Support',
            self::BugReport => 'Bug Report',
            self::FeatureRequest => 'Feature Request',
            self::Account => 'Account',
            self::GeneralInquiry => 'General Inquiry',
            self::Spam => 'Spam/Gibberish',
        };
    }
}
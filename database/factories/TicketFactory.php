<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $templates = [
            TicketCategory::GeneralQuestion->value => [
                [
                    'subject' => 'Inquiry about course enrollment deadline',
                    'body' => 'Hello, I am interested in enrolling in the Advanced Laravel course. Could you please let me know when the deadline is for the upcoming cohort? Thank you.'
                ],
                [
                    'subject' => 'Course syllabus and curriculum questions',
                    'body' => 'Hi support, is there a PDF copy of the syllabus that I can download? I want to see the specific topics covered under the database indexing section.'
                ],
                [
                    'subject' => 'Is there a student discount available?',
                    'body' => 'Hello! I am a university student and would love to take your courses. Do you offer any student discounts or scholarship opportunities? Thanks!'
                ]
            ],
            TicketCategory::TechnicalQuestion->value => [
                [
                    'subject' => 'Database connection timeout in lab env',
                    'body' => 'Hi, I am working on Lesson 12 database optimization exercises. However, I keep getting connection timeout errors when trying to connect to the lab PostgreSQL instance.'
                ],
                [
                    'subject' => 'Git clone authentication failed on repository',
                    'body' => 'Hello, I cannot clone the exercise repository. I get an authentication failed error. I have already added my SSH key to the profile page.'
                ],
                [
                    'subject' => 'Vite hot reload not working in Docker container',
                    'body' => 'Hi team, I am running the application inside a Docker container on Windows WSL2. The Vite dev server is running, but hot reload does not trigger when files change.'
                ]
            ],
            TicketCategory::RefundRequest->value => [
                [
                    'subject' => 'Refund request for duplicate purchase',
                    'body' => 'Hi, I accidentally purchased the course bundle twice due to a browser loading error. Can you please check my account and refund the duplicate payment? Thank you.'
                ],
                [
                    'subject' => 'Requesting a refund within 30-day window',
                    'body' => 'Hello, unfortunately the course content is not exactly what I was looking for. I purchased it 5 days ago and would like to request a refund under your policy.'
                ],
                [
                    'subject' => 'Accidental subscription renewal refund',
                    'body' => 'Hello, my annual subscription renewed automatically yesterday. I forgot to cancel it and would appreciate it if you could refund this renewal charge. Thanks!'
                ]
            ],
            TicketCategory::Billing->value => [
                [
                    'subject' => 'Request for invoice copy',
                    'body' => 'Hi, I need a detailed PDF invoice for my last purchase for tax reimbursement purposes. Can you please email it to me? Thanks!'
                ],
                [
                    'subject' => 'Updating credit card details',
                    'body' => 'Hello, my current card is expiring next month. Is there a secure link where I can update my billing card information before the next renewal? Thank you.'
                ],
                [
                    'subject' => 'Charged but course not activated',
                    'body' => 'Hi, my credit card was charged $149 for the course today, but the course is still showing as locked in my dashboard. Please assist.'
                ]
            ],
            TicketCategory::TechnicalSupport->value => [
                [
                    'subject' => 'Video player buffering issues',
                    'body' => 'Hi support, the videos in module 3 are buffering constantly and failing to load. My internet speed is 100Mbps and other sites work fine. Can you check this?'
                ],
                [
                    'subject' => 'Resetting two-factor authentication',
                    'body' => 'Hello, I lost my authenticator app backup codes and switched to a new phone. I am locked out of my account. Can you reset my 2FA? Thank you.'
                ],
                [
                    'subject' => 'SSL certificate error on student portal',
                    'body' => 'Hi, I am getting an "Insecure Connection" SSL error when visiting the student login page. Chrome says the certificate expired yesterday.'
                ]
            ],
            TicketCategory::BugReport->value => [
                [
                    'subject' => 'Save changes button disabled on profile settings',
                    'body' => 'Hello, when I edit my profile name, the "Save Changes" button remains greyed out and disabled. I cannot update my display name.'
                ],
                [
                    'subject' => '404 error on module 4 resource links',
                    'body' => 'Hi, the download link for the source code zip file in module 4 leads to a 404 Not Found error page. Please fix the link.'
                ],
                [
                    'subject' => 'Markdown rendering bug in code snippets',
                    'body' => 'Hi support, code block backticks are displaying as plain text instead of formatted blocks inside the comment section. It makes sharing code very hard.'
                ]
            ],
            TicketCategory::FeatureRequest->value => [
                [
                    'subject' => 'Request for dark mode option',
                    'body' => 'Hi! It would be really amazing to have a dark mode toggle on the platform. It is much easier on the eyes for late-night programming sessions.'
                ],
                [
                    'subject' => 'Subtitles or transcripts for videos',
                    'body' => 'Hello, do you plan to add subtitles or downloadable text transcripts to the video lessons? This would help non-native English speakers immensely.'
                ],
                [
                    'subject' => 'API webhooks for progress updates',
                    'body' => 'Hi, it would be awesome if we could configure a Slack webhook to receive notifications when we finish a course or pass an exam. Thanks!'
                ]
            ],
            TicketCategory::Account->value => [
                [
                    'subject' => 'Change account email address',
                    'body' => 'Hello, I would like to transfer my courses and account settings from my old university email to my personal email address. Please let me know how.'
                ],
                [
                    'subject' => 'Account deletion request',
                    'body' => 'Please delete my account and purge all of my personal data from your database. I have completed my courses and no longer need the profile.'
                ],
                [
                    'subject' => 'Password reset email not arriving',
                    'body' => 'Hi, I have requested a password reset link three times but the email never arrives in my inbox or spam folder. Please check if my account is active.'
                ]
            ],
            TicketCategory::GeneralInquiry->value => [
                [
                    'subject' => 'Corporate team training discounts',
                    'body' => 'Hi there, we have a team of 15 developers that we want to enroll in your Advanced Laravel training. Do you offer corporate pricing or bulk discounts?'
                ],
                [
                    'subject' => 'Do you provide certificate sharing for LinkedIn?',
                    'body' => 'Hello, upon completion of the course, does the platform generate a verifiable certificate link that I can add to my LinkedIn profile?'
                ],
                [
                    'subject' => 'Questions about course updates and lifetime access',
                    'body' => 'Hi, I see that you offer lifetime access. Will I get access to Laravel 13 updates when they are released next year, or will I need to buy a new version?'
                ]
            ]
        ];

        $category = $this->faker->randomElement(TicketCategory::cases());
        $templatesList = $templates[$category->value] ?? $templates[TicketCategory::GeneralInquiry->value];
        $template = $this->faker->randomElement($templatesList);

        $senderName = $this->faker->name();
        // Clean sender name for email prefix: e.g. "Dr. John Doe MD" -> "dr.john.doe.md"
        $emailPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '.', $senderName));
        $senderEmail = $emailPrefix . '@' . $this->faker->safeEmailDomain();

        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'subject' => $template['subject'],
            'body' => $template['body'],
            'status' => $this->faker->randomElement([TicketStatus::Open, TicketStatus::Resolved, TicketStatus::Closed]),
            'priority' => $this->faker->randomElement(TicketPriority::cases()),
            'category' => $category,
            'assigned_agent_id' => function () {
                // 50% chance of being assigned to a random agent
                if (fake()->boolean(50)) {
                    return User::where('role', Role::Agent)->inRandomOrder()->first()?->id;
                }
                return null;
            },
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }
}

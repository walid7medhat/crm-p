<?php

namespace App\Notifications;

use App\Models\ListingAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingAccessRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ListingAccessRequest $accessRequest,
        public string $action, // 'requested', 'approved', 'rejected', 'cancelled', 'converted',viewing,edit viewing time,'in progress'
         public array $additionalData = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\ListingAccessRequestNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        $listing = $this->accessRequest->listing;
        $salesPerson = $this->accessRequest->requestedBy;

        $data = [
            'message' => $this->getMessage(),
            'access_request_id' => $this->accessRequest->id,
            'listing_id' => $listing->id,
            'listing_title' => $listing->area->name,
            'listing_unit_number' => $listing->unit_number,
            'request_type' => $this->accessRequest->request_type,
            'request_type_label' => $this->getRequestTypeLabel(),
            'sales_person_id' => $salesPerson->id,
            'sales_person_name' => $salesPerson->name,
            'notification_type' => $this->action,
            'reason' => $this->accessRequest->reason,
            'status' => $this->accessRequest->status,
             'old_date' => $this->additionalData['old_date'] ?? null,
        'old_time' => $this->additionalData['old_time'] ?? null,
        'new_date' => $this->additionalData['new_date'] ?? null,
        'new_time' => $this->additionalData['new_time'] ?? null,
            'timestamp' => now()->toISOString(),
        ];

        // Add viewing details if request is for viewing
        if ($this->accessRequest->request_type === 'viewing' && $this->accessRequest->viewing_date) {
            $data['viewing_details'] = [
                'date' => $this->accessRequest->viewing_date->format('Y-m-d'),
                'time' => $this->accessRequest->viewing_time,
                'type' => $this->accessRequest->viewing_type,
                'formatted_date' => $this->accessRequest->viewing_date->format('d M Y'),
                'formatted_time' => $this->formatTime($this->accessRequest->viewing_time),
            ];
        }

        // Add response details if available
        if ($this->accessRequest->owner_response) {
            $data['owner_response'] = $this->accessRequest->owner_response;
        }

        // Add cancellation details if cancelled
        if ($this->accessRequest->status === 'cancelled') {
            $data['cancelled_at'] = $this->accessRequest->cancelled_at;
            $data['cancelled_by'] = $this->accessRequest->cancelled_by;
        }

        // Add conversion details if converted
        if ($this->accessRequest->status === 'converted') {
            $data['converted_at'] = $this->accessRequest->converted_at;
            $data['converted_by'] = $this->accessRequest->convertedBy?->name;
            $data['conversion_notes'] = $this->accessRequest->conversion_notes;
        }

        return $data;
    }

    public function toDatabase($notifiable)
    {
        return $this->toArray($notifiable);
    }

    private function getRequestTypeLabel(): string
    {
        return match($this->accessRequest->request_type) {
            'owner_data' => 'Owner Information',
            'unit_number' => 'Unit Number',
            'viewing' => 'Property Viewing',
            default => 'Access Request'
        };
    }

    private function formatTime($time): string
    {
        if (!$time) return '';
        
        try {
            $time = \Carbon\Carbon::createFromFormat('H:i:s', $time);
            return $time->format('h:i A');
        } catch (\Exception $e) {
            return $time;
        }
    }

    private function getMessage(): string
    {
        $salesPerson = $this->accessRequest->requestedBy->name;
        $listing = $this->accessRequest->listing->area->name;
        $requestType = $this->getRequestTypeLabel();

        switch ($this->action) {
            case 'requested':
                $message = "New {$requestType} request from {$salesPerson} for {$listing}";
                
                // Add viewing details to message if viewing request
                if ($this->accessRequest->request_type === 'viewing' && $this->accessRequest->viewing_date) {
                    $viewingDate = $this->accessRequest->viewing_date->format('d M Y');
                    $viewingTime = $this->formatTime($this->accessRequest->viewing_time);
                    $viewingType = $this->accessRequest->viewing_type === 'in_person' ? 'In Person' : 'Virtual';
                    
                    $message .= " - Scheduled for {$viewingDate} at {$viewingTime} ({$viewingType})";
                }
                return $message;

            case 'approved':
                $message = "Your {$requestType} request for {$listing} has been approved";
                
                // Add viewing details if applicable
                if ($this->accessRequest->request_type === 'viewing') {
                    $viewingDate = $this->accessRequest->viewing_date->format('d M Y');
                    $viewingTime = $this->formatTime($this->accessRequest->viewing_time);
                    $message .= "! Viewing confirmed for {$viewingDate} at {$viewingTime}";
                }
                return $message;
             case 'in_progress':
                $message = "Your {$requestType} request for {$listing} is in progress";
                
                // Add viewing details if applicable
                if ($this->accessRequest->request_type === 'viewing') {
                    $viewingDate = $this->accessRequest->viewing_date->format('d M Y');
                    $viewingTime = $this->formatTime($this->accessRequest->viewing_time);
                    $message .= "! Viewing for {$viewingDate} at {$viewingTime}";
                }
                return $message;

            case 'rejected':
                $message = "Your {$requestType} request for {$listing} has been declined";
                
                // Add reason if provided
                if ($this->accessRequest->owner_response) {
                    $message .= ". Reason: " . substr($this->accessRequest->owner_response, 0, 100);
                    if (strlen($this->accessRequest->owner_response) > 100) {
                        $message .= "...";
                    }
                }
                return $message;

            case 'cancelled':
                $cancelledBy = $this->accessRequest->cancelled_by === $this->accessRequest->requested_by 
                    ? "by you" 
                    : "by the agent";
                
                $message = "{$requestType} request for {$listing} was cancelled {$cancelledBy}";
                
                // Add viewing details if cancelled viewing
                if ($this->accessRequest->request_type === 'viewing') {
                    $viewingDate = $this->accessRequest->viewing_date ? $this->accessRequest->viewing_date->format('d M Y') : '';
                    $viewingTime = $this->formatTime($this->accessRequest->viewing_time);
                    $message .= ". Viewing was scheduled for {$viewingDate} at {$viewingTime}";
                }
                return $message;

            case 'converted':
                $convertedBy = $this->accessRequest->convertedBy?->name ?? 'You';
                return "{$requestType} request for {$listing} marked as converted by {$convertedBy}";
            case 'time_updated':
              return "Viewing time for your request has been updated from {$this->additionalData['old_date']} {$this->additionalData['old_time']} to {$this->additionalData['new_date']} {$this->additionalData['new_time']}";
            default:
                return "Notification about {$listing}";
        }
    }

    // Mail notification method
    public function toMail($notifiable)
    {
        $subject = match($this->action) {
            'requested' => '📋 New Access Request',
            'approved' => '✅ Request Approved',
            'rejected' => '❌ Request Declined',
            'cancelled' => '🗑️ Request Cancelled',
            'in_progress' => '🔄 Viewing In Progress',
            'converted' => '🎯 Request Converted',
            default => 'Listing Access Request Update'
        };

        $mail = (new MailMessage)
            ->subject($subject . " - " . $this->getRequestTypeLabel())
            ->greeting('Hello ' . $notifiable->name . '!');

        // Main message
        $mail->line($this->getMessage());

        // Add viewing details to email if viewing request
        if ($this->accessRequest->request_type === 'viewing' && $this->accessRequest->viewing_date) {
            $mail->line(' ')
                ->line('**Viewing Details:**')
                ->line('Date: ' . $this->accessRequest->viewing_date->format('d M Y'))
                ->line('Time: ' . $this->formatTime($this->accessRequest->viewing_time))
                ->line('Type: ' . ($this->accessRequest->viewing_type === 'in_person' ? 'In Person' : 'Virtual'));
            
            if ($this->accessRequest->viewing_notes) {
                $mail->line('Notes: ' . $this->accessRequest->viewing_notes);
            }
        }

        // Add property details
        $mail->line(' ')
            ->line('**Property Details:**')
            ->line('Title: ' . $this->accessRequest->listing->title)
            ->line('Unit: ' . $this->accessRequest->listing->unit_number)
            ->line('Type: ' . $this->accessRequest->listing->property_type?->name);

        // Add action button based on user role
        $isAgent = $notifiable->id === $this->accessRequest->listing->agent_id;
        
        if ($isAgent && $this->action === 'requested') {
            $mail->action('Respond to Request', url('/dashboard/listing-access-requests/' . $this->accessRequest->id));
        } else {
            $mail->action('View Details', url('/properties/' . $this->accessRequest->listing_id));
        }

        $mail->line('Thank you for using our platform!');

        return $mail;
    }
}
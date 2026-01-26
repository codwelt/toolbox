<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ToolFeedbackMail extends Mailable
{
    public string $messageText;
    public ?string $contactEmail;
    public string $toolName;
    public string $toolUrl;
    public ?string $userAgent;
    public ?string $ipAddress;

    public function __construct(
        string $messageText,
        ?string $contactEmail,
        string $toolName,
        string $toolUrl,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ) {
        $this->messageText = $messageText;
        $this->contactEmail = $contactEmail;
        $this->toolName = $toolName;
        $this->toolUrl = $toolUrl;
        $this->userAgent = $userAgent;
        $this->ipAddress = $ipAddress;
    }

    public function build()
    {
        return $this->subject("Nuevo mensaje desde {$this->toolName}")
            ->view('emails.tool-feedback')
            ->with([
                'messageText' => $this->messageText,
                'contactEmail' => $this->contactEmail,
                'toolName' => $this->toolName,
                'toolUrl' => $this->toolUrl,
                'userAgent' => $this->userAgent,
                'ipAddress' => $this->ipAddress,
            ]);
    }
}

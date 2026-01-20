<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;
    public $subjectText;
    public $body;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subjectText,$body)
    {
        $this->subjectText=$subjectText;
        $this->body=$body;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subjectText)
                ->view('emails.generic')
                ->with(['body'=>$this->body]);
    }
}

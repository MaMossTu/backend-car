<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels;
        public $email;
    public $data;
    public $title;
    public $type;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $data, $title, $type)
    {
        $this->email = $email;
        $this->data = $data;
        $this->title = $title;
        $this->type = $type;
        // dd($type);

    }

    public function build()
    {
        return $this->view('send_mail')
                    ->from('sup.edupol@gmail.com')
                    ->subject('Asap driver: ' . $this->title)
                    ->with([
                        'text' => $this->data,
                        'title' => $this->title,
                        'type' => $this->type,
                    ]);
    }

}

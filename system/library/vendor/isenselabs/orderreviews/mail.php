<?php
namespace Vendor\iSenseLabs\OrderReviews;

class Mail {
    protected $adaptor;
    protected $to;
    protected $from;
    protected $sender;
    protected $reply_to;
    protected $subject;
    protected $text;
    protected $html;
    protected $attachments = array();
    protected $bcc;       
    public $parameter;
    public $smtp_hostname;
    public $smtp_username;
    public $smtp_password;
    public $smtp_port = 25;
    public $smtp_timeout = 5;
    public $verp = false;
    public $before_data_terminator;

    public function __construct($adaptor = 'mail') {
        $class = 'Vendor\\iSenseLabs\\OrderReviews\\Mail\\' . $adaptor;

        if (class_exists($class)) {
            $this->adaptor = new $class();
        } else {
            trigger_error('Error: Could not load mail adaptor ' . $adaptor . '!');
            exit();
        }	
    }

    public function setTo($to) {
        $this->to = $to;
    }

    public function setFrom($from) {
        $this->from = $from;
    }

    public function setBcc($bcc) {
        $this->bcc = $bcc;
    }

    public function setSender($sender) {
        $this->sender = $sender;
    }

    public function setReplyTo($reply_to) {
        $this->reply_to = $reply_to;
    }

    public function setSubject($subject) {
        $this->subject = $subject;
    }

    public function setText($text) {
        $this->text = $text;
    }

    public function setHtml($html) {
        $this->html = $html;
    }

    public function addAttachment($filename) {
        $this->attachments[] = $filename;
    }

    public function setBeforeDataTerminator($callback) {
        $this->before_data_terminator = $callback;
    }

    public function send() {
        if (!$this->to) {
            throw new \Exception('Error: E-Mail to required!');
        }

        if (!$this->from) {
            throw new \Exception('Error: E-Mail from required!');
        }

        if (!$this->sender) {
            throw new \Exception('Error: E-Mail sender required!');
        }

        if (!$this->subject) {
            throw new \Exception('Error: E-Mail subject required!');
        }

        if ((!$this->text) && (!$this->html)) {
            throw new \Exception('Error: E-Mail message required!');
        }

        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'adaptor') {
                continue;
            }

            $this->adaptor->$key = $value;
        }

        return $this->adaptor->send();
    }
}

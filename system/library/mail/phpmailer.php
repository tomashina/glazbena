<?php
namespace Mail;

include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/Exception.php');
include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/PHPMailer.php');
include_once(DIR_SYSTEM . 'library/shared/phpmailer/src/SMTP.php');

class Phpmailer {
	private $debugMode = false;

	public function send() {
		if (property_exists($this, 'mail_send') && $this->mail_send === false) {
			return true; // unsubscribed
		}

		if (property_exists($this, 'mail_queue') && $this->mail_queue) {
			return true; // send later via cron
		}

		if (!$this->to) {
			trigger_error('Error: E-Mail to required!');
			exit();
		}

		if (!$this->from) {
			trigger_error('Error: E-Mail from required!');
			exit();
		}

		if (!$this->sender) {
			trigger_error('Error: E-Mail sender required!');
			exit();
		}

		if (!$this->subject) {
			trigger_error('Error: E-Mail subject required!');
			exit();
		}

		if ((!$this->text) && (!$this->html)) {
			trigger_error('Error: E-Mail message required!');
			exit();
		}

		$mail = new \PHPMailer\PHPMailer\PHPMailer($this->debugMode);

		$mail->CharSet = "UTF-8";
		$mail->XMailer = '';

		$mail->Subject = $this->subject;

        if (is_array($this->to)) {
            foreach ($this->to as $to){
                $mail->AddAddress($to);
            }
        } else {
            $mail->AddAddress($this->to);
        }

        $mail->SetFrom($this->from, $this->sender);

        if (!empty($this->reply_to)){
            $mail->AddReplyTo($this->reply_to, $this->sender);
        }

       /* if ($this->cc){
            if (is_array($this->cc)) {
                foreach ($this->cc as $cc){
                    $mail->AddCC($cc);
                }
            } else {
                $mail->AddCC($this->cc);
            }
        }

        if ($this->bcc){
            if (is_array($this->bcc)) {
                foreach ($this->bcc as $bcc){
                    $mail->AddBCC($bcc);
                }
            } else {
                $mail->AddBCC($this->bcc);
            }
        }*/

		if (!$this->html) {
			$mail->Body = $this->text;
		} else {
			$mail->MsgHTML($this->html);
			if ($this->text) {
				$mail->AltBody = $this->text;
			}
		}

		if($this->attachments){
			foreach ($this->attachments as $attachment) {
				if (file_exists($attachment)) {
					$mail->AddAttachment($attachment);
				}
			}
		}

		if (!empty($this->smtp_username)  && !empty($this->smtp_password)) {
			$mail->IsSMTP();
			$mail->SMTPDebug = ($this->debugMode) ? true : false;

			if($this->smtp_port == '587'){
				$mail->SMTPAuth = true;
				$mail->SMTPSecure = "tls";
			} elseif ($this->smtp_port == '465') {
				$mail->SMTPAuth = true;
				$mail->SMTPSecure = "ssl";
			} else {
				$mail->SMTPAuth = false;
			}

			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);

			$mail->Host = $this->smtp_hostname;
			$mail->Port = $this->smtp_port;

			$mail->Username = html_entity_decode($this->smtp_username, ENT_QUOTES, 'UTF-8');
			$mail->Password = html_entity_decode($this->smtp_password, ENT_QUOTES, 'UTF-8');
		}

		if($this->debugMode){
			try {
				$mail->Send();
			} catch(\PHPMailer\PHPMailer\Exception $e) {
				trigger_error($e->errorMessage());
			} catch(\Exception $e) {
				trigger_error($e->getMessage());
			}
		} else {
			$mail->Send();
		}
	}

}

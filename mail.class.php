<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    private $mail = null;

    public function __construct(){
        $this->mail = new PHPMailer(true);
        $this->mail->SMTPDebug = 2;
        $this->mail->isSMTP();
        $this->mail->Host = MAIL_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = MAIL_USERNAME;
        $this->mail->Password = MAIL_PASSWORD;
        $this->mail->SMTPSecure = MAIL_ENCRYPTION;
        $this->mail->Port = MAIL_PORT;
    }

    public function notify($subject, $body, $email){
        $this->mail->Subject = $subject;
        $this->mail->Body = $body;
        $this->mail->setFrom(MAIL_ADDRESS, MAIL_NAME);
        $this->mail->addAddress($email);

        foreach (CC_LIST as $cc) {
            $this->mail->AddCC($cc);
        }
        $this->mail->isHTML(false);
        $this->mail->send();
    }
}
?>
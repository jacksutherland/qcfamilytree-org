<?php
 
// EDIT THE 2 LINES BELOW AS REQUIRED
//$email_to = "jacksutherl@gmail.com";
$email_subject = "Website Contact Submission";

function died($error) {
    // your error code can go here
    //echo "We are very sorry, but there were error(s) found with the form you submitted. ";
    //echo "These errors appear below.<br /><br />";
    echo $error;
    //echo "Please go back and fix these errors.<br /><br />";
    die();
}

if(strlen($_POST['firstname']) > 0) // Simple HPot Logic
{
    died('We are sorry, but there appears to be a problem with the form you submitted.');  
}

// validation expected data exists
if(!isset($_POST['first']) ||
    !isset($_POST['last']) ||
    !isset($_POST['toemail']) ||
    !isset($_POST['email']) ||
    !isset($_POST['phone']) ||
    !isset($_POST['comments'])) {
    died('We are sorry, but there appears to be a problem with the form you submitted.');       
}

$email_to = $_POST['toemail'];
$first_name = $_POST['first']; // required
$last_name = $_POST['last']; // required
$email_from = $_POST['email']; // required
$telephone = $_POST['phone']; // not required
$comments = $_POST['comments']; // required

$error_message = "";
$email_exp = '/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/';

if(!preg_match($email_exp,$email_from)) {
  $error_message .= 'The Email Address you entered is invalid.<br />';
}

  $string_exp = "/^[A-Za-z .'-]+$/";

if(!preg_match($string_exp,$first_name)) {
  $error_message .= 'A valid First Name is required.<br />';
}

if(!preg_match($string_exp,$last_name)) {
  $error_message .= 'A valid Last Name is required.<br />';
}

// if(strlen($comments) < 2) {
//   $error_message .= 'The Comments you entered are invalid.<br />';
// }

if(strlen($error_message) > 0) {
  died($error_message);
}

$email_message = "Form details below.\n\n";

 
function clean_string($string) {
  $bad = array("content-type","bcc:","to:","cc:","href");
  return str_replace($bad,"",$string);
}

$email_message .= "First Name: ".clean_string($first_name)."\n";
$email_message .= "Last Name: ".clean_string($last_name)."\n";
$email_message .= "Email: ".clean_string($email_from)."\n";
$email_message .= "Telephone: ".clean_string($telephone)."\n";
$email_message .= "Comments: ".clean_string($comments)."\n";
 
// create email headers
$headers = 'From: '.$email_from."\r\n".
'Reply-To: '.$email_from."\r\n" .
'X-Mailer: PHP/' . phpversion();
@mail($email_to, $email_subject, $email_message, $headers);  

echo "success"

?>
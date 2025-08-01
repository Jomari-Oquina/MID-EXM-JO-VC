<?php
require_once __DIR__. '/../../../database/dbconfig.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

include_once __DIR__.'/../../../configuration/settings-configuration.php';
require_once __DIR__. '/../../vendor/autoload.php';


class AGENT
{

 private $conn;
 
 public function __construct()
 {
  $database = new Database();
  $db = $database->dbConnection();
  $this->conn = $db;
    }
 
 public function runQuery($sql)
 {
  $stmt = $this->conn->prepare($sql);
  return $stmt;
 }

 public function siteSecretKey() {
    $config = new SystemConfig();
    $SSkey = $config->getSSKey();
    return $SSkey;
}

public function smtpEmail(){
  $smtp = new SystemConfig();
  $smtp_email = $smtp->getSmtpEmail();
  return $smtp_email;
}

public function smtpPassword(){
  $smtp = new SystemConfig();
  $smtp_password = $smtp->getSmtpPassword();
  return $smtp_password;
}

public function systemName(){
  $systemname = new SystemConfig();
  $Sname = $systemname->getSystemName();
  return $Sname;
}

public function emailConfig(){
  $email = $this->smtpEmail();
  $password = $this->smtpPassword();
  $system_name = $this->systemName();
}

public function mainUrl(){
  $main_url = new MainUrl();
  $URL = $main_url->getUrl();
  return $URL;
}

public function systemLogo(){
  $smtp = new SystemConfig();
  $systemLogo = $smtp->getSystemLogo();
  return $systemLogo;
}

 public function lasdID()
 {
  $stmt = $this->conn->lastInsertId();
  return $stmt;
 }
 
 public function register($first_name, $middle_name, $last_name, $email, $valid_id, $hash_password, $tokencode, $user_type, $package_type)
 {
  try
  {       
   $password = md5($hash_password);
   $stmt = $this->conn->prepare("INSERT INTO users(first_name, middle_name, last_name, email, password, valid_id, tokencode, user_type, package_id) 
                                        VALUES(:first_name, :middle_name, :last_name, :email, :password, :valid_id, :tokencode, :user_type, :package_id)");
   
   $stmt->bindparam(":first_name",$first_name);
   $stmt->bindparam(":middle_name",$middle_name);
   $stmt->bindparam(":last_name",$last_name);
   $stmt->bindparam(":email",$email);
   $stmt->bindparam(":valid_id",$valid_id);
   $stmt->bindparam(":password",$password);
   $stmt->bindparam(":tokencode",$tokencode);
   $stmt->bindparam(":user_type",$user_type);
   $stmt->bindparam(":package_id",$package_type);

   $stmt->execute(); 
   return $stmt;
  }
  catch(PDOException $ex)
  {
   echo $ex->getMessage();
  }
 }
 
 public function login($email,$hash_password)
 {
  try
  {
   $stmt = $this->conn->prepare("SELECT * FROM users WHERE email=:email_id AND account_status = :account_status AND user_type = :user_type");
   $stmt->execute(array(":email_id"=>$email , ":account_status" => "active", "user_type" => 2));
   $userRow=$stmt->fetch(PDO::FETCH_ASSOC);
   

   if($stmt->rowCount() == 1)
   {
    if($userRow['status']=="Y")
    {
     if($userRow['password']==md5($hash_password))
     {
      DATE_DEFAULT_TIMEZONE_SET('Asia/Manila');
      $activity = "Has successfully signed in";
      $date_now = date("Y-m-d h:i:s A");
      $user_id = $userRow['id'];
  
      $stmt = $this->conn->prepare("INSERT INTO logs (user_id, activity) VALUES (:user_id, :activity)");
      $stmt->execute(array(":user_id"=>$user_id,":activity"=>$activity));
      $_SESSION['agent_session'] = $userRow['id'];
      return true;
     }
     else
     {
      echo "$email";
      $_SESSION['status_title'] = "Oops !";
      $_SESSION['status'] = "Email or Password is incorrect.";
      $_SESSION['status_code'] = "error";
      $_SESSION['status_timer'] = 1000000;
      header("Location: ../../../signin.php");
      exit;
     }
    }
    else if($userRow['status']=="D"){
      $_SESSION['status_title'] = "Sorry !";
      $_SESSION['status'] = "Your application as an agent is decline, sorry for inconvenience.";
      $_SESSION['status_code'] = "error";
      $_SESSION['status_timer'] = 10000000;
     header("Location: ../../../signin.php");
     exit;
    }
    else
    {
      $_SESSION['status_title'] = "Sorry !";
      $_SESSION['status'] = "Your account is still pending verification. Please be patient as our admin reviews your account.";
      $_SESSION['status_code'] = "error";
      $_SESSION['status_timer'] = 10000000;
     header("Location: ../../../signin.php");
     exit;
    } 
   }
   else
   {
    $_SESSION['status_title'] = "Sorry !";
    $_SESSION['status'] = "No account found or your account has been remove!";
    $_SESSION['status_code'] = "error";
    $_SESSION['status_timer'] = 10000000;
   header("Location: ../../../signin.php");
    exit;
   }  
  }
  catch(PDOException $ex)
  {
   echo $ex->getMessage();
  }
 }
 
 
 public function isUserLoggedIn()
 {
  if(isset($_SESSION['agent_session']))
  {
   return true;
  }
 }
 
 public function redirect($url)
 {
  header("Location: $url");
 }
 
 public function logout()
 {
  unset($_SESSION['agent_session']);

  $_SESSION['status_title'] = 'Logout!';
  $_SESSION['status'] = 'Thank you for using MAGRENT';
  $_SESSION['status_code'] = 'success';
  $_SESSION['status_timer'] = 40000;    
  header('Location: ../../../signin.php');
 }

 function send_mail($email,$message,$subject,$smtp_email,$smtp_password,$system_name)
 {      
  $mail = new PHPMailer();
  $mail->IsSMTP(); 
  $mail->SMTPDebug  = 0;                     
  $mail->SMTPAuth   = true;                  
  $mail->SMTPSecure = "tls";                 
  $mail->Host       = "smtp.gmail.com";      
  $mail->Port       = 587;             
  $mail->AddAddress($email);
  $mail->Username = $smtp_email;  
  $mail->Password= $smtp_password;          
  $mail->SetFrom($smtp_email, $system_name);
  $mail->Subject    = $subject;
  $mail->MsgHTML($message);
  $imagePath = __DIR__ . '/../../../src/images/main_logo/logo.png';
  $mail->AddEmbeddedImage($imagePath, 'logo', 'logo.png');
  $mail->Send();
 } 
}
?>

<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code for managing user accounts and permissions.

//Check whether a user has permissions for an action.
function userAllow($task) {
  if (isset($_SESSION["user"])) {
    $rs = dbQuery("SELECT * FROM `users` WHERE `email` = ?;", array($_SESSION["user"]));
    $numrows = ($rs) ? mysqli_num_rows($rs) : 0;
    if ($numrows == 1) {
      $user = mysqli_fetch_assoc($rs);
      if ($user["id"] == 1 || $user["role"] == "administer") {
        return TRUE;
      } else {
        return FALSE;
      }
    } else {
      return FALSE;
    }
  } else {
    return FALSE;
  }
}

function login(){
  global $db;
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  $rs = dbQuery("SELECT * FROM `users` WHERE email = ?;", array($email));
  $numRows = ($rs) ? mysqli_num_rows($rs) : 0;
  if($numRows  == 1){
    $row = mysqli_fetch_assoc($rs);
    //Passwords set before prepared statements were introduced were hashed after SQL escaping
    if(password_verify($password,$row['password']) || password_verify($db->real_escape_string($password),$row['password'])){
      $_SESSION["user"] = $email;
    } else {
      print t("Wrong password");
    }
  } else {
    print t("No matching user found");
  }
}

function logout(){
  global $db;
  unset($_SESSION["user"]);
  print "Logged out.";
}

function loadUser($email) {
  $result = dbQuery("SELECT * FROM `users` WHERE `email` = ?;", array($email));
  if ($result) {
    $ret = $result->fetch_assoc();
    unset($ret["password"]);
    return($ret);
  }
  return(null);
}

function createUser(){
  $firstName = $_POST['first_name'];
  $surName   = $_POST['surname'];
  $email     = $_POST['email'];
  $password  = $_POST['password'];

  $options = array("cost"=>4);
  $hashPassword = password_hash($password,PASSWORD_BCRYPT,$options);

  $sql = "INSERT INTO `users` (first_name, last_name, email, password) VALUES (?, ?, ?, ?);";
  $result = dbQuery($sql, array($firstName, $surName, $email, $hashPassword));
  if($result) {
    print t("User created");
  }
}

function editUser() {
  $error = "";

  $first_name  = $_POST['first_name'];
  $last_name   = $_POST['last_name'];
  $o_password  = $_POST['old_password'];
  $n_password1 = $_POST['new_password1'];
  $n_password2 = $_POST['new_password2'];

  if (!($o_password == "" && $n_password1 == "" && $n_password2 == "")) {
    if ($o_password == "") {
      $error .= "<p>Current password must be provided.</p>";
    }
    if ($n_password1 == "" || $n_password2 == "") {
      $error .= "<p>You must repeat the new password.</p>";
    }
    if (!($n_password1 == $n_password2)) {
      $error .= "<p>New password and repeat password must match.</p>";
    }
  }

  if ($error != "") {
    $out  = "<div class='error'>";
    $out .= $error;
    $out .= "</div>";
    print $out;
  } else {
    $options = array("cost"=>4);
    if ($n_password1 == "") {
      $hashPassword = null;
    } else {
      $hashPassword = password_hash($n_password1,PASSWORD_BCRYPT,$options);
    }

    $sql  = "UPDATE `users` SET `first_name` = ?, `last_name` = ?";
    $params = array($first_name, $last_name);
    if ($hashPassword != null) {
      $sql .= ", `password` = ?";
      $params[] = $hashPassword;
    }
    $sql .= " WHERE `email` = ?;";
    $params[] = $_SESSION["user"];
    dbQuery($sql, $params);
    $_SESSION["user"] = "email";
  }
}

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

//CSRF token for this session, created on first use
function csrfToken() {
  if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
  }
  return($_SESSION["csrf_token"]);
}

//Hidden form field carrying the CSRF token
function csrfField() {
  return("<input type='hidden' name='csrf_token' value='".h(csrfToken())."'>");
}

//Check that a submitted form carries this session's CSRF token
function csrfValid() {
  return(isset($_SESSION["csrf_token"]) && isset($_POST["csrf_token"]) && is_string($_POST["csrf_token"])
    && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]));
}

//Check a password against a user's database row, upgrading old or weak hashes
function verifyPassword($password, $row) {
  global $db;
  if (password_verify($password, $row["password"])) {
    $rehash = password_needs_rehash($row["password"], PASSWORD_DEFAULT);
  } elseif (password_verify($db->real_escape_string($password), $row["password"])) {
    //Passwords set before prepared statements were introduced were hashed after SQL escaping
    $rehash = TRUE;
  } else {
    return(FALSE);
  }
  if ($rehash) {
    dbQuery("UPDATE `users` SET `password` = ? WHERE `id` = ?;", array(password_hash($password, PASSWORD_DEFAULT), $row["id"]));
  }
  return(TRUE);
}

//Log in from the login form. Must run before any output is sent.
//Returns a message for the login page, or NULL on success.
function login(){
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  $rs = dbQuery("SELECT * FROM `users` WHERE email = ?;", array($email));
  $numRows = ($rs) ? mysqli_num_rows($rs) : 0;
  if($numRows  == 1){
    $row = mysqli_fetch_assoc($rs);
    if(verifyPassword($password, $row)){
      //A new session id on login prevents session fixation
      session_regenerate_id(TRUE);
      $_SESSION["user"] = $email;
      return(null);
    } else {
      return("Wrong password");
    }
  } else {
    return("No matching user found");
  }
}

//Log out. Must run before any output is sent.
function logout(){
  unset($_SESSION["user"]);
  session_regenerate_id(TRUE);
  return("Logged out.");
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

  $hashPassword = password_hash($password,PASSWORD_DEFAULT);

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
    } else {
      $rs = dbQuery("SELECT * FROM `users` WHERE `email` = ?;", array($_SESSION["user"]));
      $row = ($rs) ? $rs->fetch_assoc() : null;
      if ($row == null || !verifyPassword($o_password, $row)) {
        $error .= "<p>Current password is incorrect.</p>";
      }
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
    if ($n_password1 == "") {
      $hashPassword = null;
    } else {
      $hashPassword = password_hash($n_password1,PASSWORD_DEFAULT);
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
  }
}

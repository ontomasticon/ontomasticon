<?php
// Ontomasticon: a simple, lightweight, PHP-based ontology browser.
// Department of Information Retrieval
//
// Code for managing user accounts and permissions.

//Failed logins allowed within LOGIN_WINDOW seconds, per email address and per IP address
define("LOGIN_WINDOW", 900);
define("LOGIN_MAX_PER_EMAIL", 5);
define("LOGIN_MAX_PER_IP", 20);

//Password the installer gives the admin account
define("DEFAULT_PASSWORD", "password");

//Roles a user can have and the tasks each allows. "*" allows every task.
function userRoles() {
  return(array(
    "administer" => array("name" => "Admin", "tasks" => array("*")),
    "editor" => array("name" => "Editor", "tasks" => array("edit-terms", "edit-cvs")),
    "create-cv" => array("name" => "Editor and CV creator", "tasks" => array("edit-terms", "edit-cvs", "create-cv"))
  ));
}

//Display name of a role
function roleName($role) {
  $roles = userRoles();
  return(isset($roles[$role]) ? $roles[$role]["name"] : "None");
}

//Dropdown for choosing a role
function roleSelect($current = null) {
  $out  = "<select name='role'>";
  $out .= "<option value=''>".h(t("None"))."</option>";
  foreach (userRoles() as $key => $role) {
    $selected = ($key == $current) ? " selected" : "";
    $out .= "<option value='".h($key)."'".$selected.">".h(t($role["name"]))."</option>";
  }
  $out .= "</select>";
  return($out);
}

//Check whether the logged in user may perform a task.
function userAllow($task) {
  static $users = array();
  if (!isset($_SESSION["user"])) {
    return FALSE;
  }
  $email = $_SESSION["user"];
  //Look each user up once per request, as this is called many times per page
  if (!array_key_exists($email, $users)) {
    $rs = dbQuery("SELECT `id`, `role` FROM ".table("users")." WHERE `email` = ?;", array($email));
    $users[$email] = ($rs && mysqli_num_rows($rs) == 1) ? mysqli_fetch_assoc($rs) : null;
  }
  $user = $users[$email];
  if ($user == null) {
    return FALSE;
  }
  //User 1 is the installation's admin account and always has full access
  if ($user["id"] == 1) {
    return TRUE;
  }
  $roles = userRoles();
  if (!isset($roles[$user["role"]])) {
    return FALSE;
  }
  $tasks = $roles[$user["role"]]["tasks"];
  return(in_array("*", $tasks) || in_array($task, $tasks));
}

//How long browsers and crawlers may keep a page for a visitor without a session, in seconds
define("PUBLIC_CACHE_SECONDS", 300);

//Whether a request needs a session: when the visitor already has one (for example because they are logged in),
//submits a form, visits the login, user or administration pages, or chooses one of the site's languages, which is
//remembered for the rest of the visit. Other visitors, including crawlers, get no session cookie, and pages they can cache.
function sessionNeeded($pageInfo) {
  if (isset($_COOKIE[session_name()])) {
    return(TRUE);
  }
  if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    return(TRUE);
  }
  if (in_array($pageInfo["page_type"], array("user", "admin"), TRUE)) {
    return(TRUE);
  }
  return(isset($_GET["lang"]) && in_array($_GET["lang"], siteLanguages(), TRUE));
}

//Start the session, if it hasn't been started. Must run before any output is sent. The cookie is limited to the
//site's own path, so sites installed in different subdirectories of one domain don't share a login.
function startSession() {
  if (session_status() == PHP_SESSION_ACTIVE) {
    return;
  }
  session_start(array(
    "cookie_httponly" => TRUE,
    "cookie_samesite" => "Lax",
    "cookie_secure" => requestIsHttps(),
    "cookie_path" => basePath()."/",
    "use_strict_mode" => TRUE
  ));
}

//CSRF token for this session, created on first use
function csrfToken() {
  //Forms are on pages that start a session, but a customised template might put one elsewhere
  if (session_status() != PHP_SESSION_ACTIVE && !headers_sent()) {
    startSession();
  }
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
    dbQuery("UPDATE ".table("users")." SET `password` = ? WHERE `id` = ?;", array(password_hash($password, PASSWORD_DEFAULT), $row["id"]));
  }
  return(TRUE);
}

//Check whether too many logins have recently failed for this email address or IP address.
//If the login_attempts table doesn't exist yet (before the 0.3 update) logins are not limited.
function loginLocked($email, $ip) {
  $since = time() - LOGIN_WINDOW;
  //Attempts older than the window are no longer needed
  dbQuery("DELETE FROM ".table("login_attempts")." WHERE `attempted` < ?;", array($since));
  $rs = dbQuery("SELECT SUM(`email` = ?) AS `email_count`, SUM(`ip` = ?) AS `ip_count` FROM ".table("login_attempts").";", array($email, $ip));
  $row = ($rs) ? $rs->fetch_assoc() : null;
  if ($row == null) {
    return(FALSE);
  }
  return($row["email_count"] >= LOGIN_MAX_PER_EMAIL || $row["ip_count"] >= LOGIN_MAX_PER_IP);
}

//Log in from the login form. Must run before any output is sent.
//Returns a message for the login page, or NULL on success.
function login(){
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  $ip = clientIP();

  if (loginLocked($email, $ip)) {
    return("Too many failed logins. Please try again later.");
  }

  $rs = dbQuery("SELECT * FROM ".table("users")." WHERE email = ?;", array($email));
  $row = ($rs && mysqli_num_rows($rs) == 1) ? mysqli_fetch_assoc($rs) : null;
  if ($row != null && verifyPassword($password, $row)) {
    dbQuery("DELETE FROM ".table("login_attempts")." WHERE `email` = ?;", array($email));
    //A new session id on login prevents session fixation
    session_regenerate_id(TRUE);
    $_SESSION["user"] = $email;
    //Accounts still using the installer's password must change it before doing anything else
    if ($password === DEFAULT_PASSWORD) {
      $_SESSION["must_change_password"] = TRUE;
    }
    return(null);
  }
  if ($row == null) {
    //Take as long as checking a password, so response times don't reveal which emails have accounts
    password_hash($password, PASSWORD_DEFAULT);
  }
  dbQuery("INSERT INTO ".table("login_attempts")." (`email`, `ip`, `attempted`) VALUES (?, ?, ?);", array($email, $ip, time()));
  //The same message either way, so it doesn't reveal which emails have accounts
  return("Incorrect email address or password");
}

//Log out. Must run before any output is sent.
function logout(){
  unset($_SESSION["user"]);
  unset($_SESSION["must_change_password"]);
  session_regenerate_id(TRUE);
  return("Logged out.");
}

function loadUser($email) {
  $result = dbQuery("SELECT * FROM ".table("users")." WHERE `email` = ?;", array($email));
  if ($result) {
    $ret = $result->fetch_assoc();
    unset($ret["password"]);
    return($ret);
  }
  return(null);
}

function getUser($id) {
  $result = dbQuery("SELECT `id`, `first_name`, `last_name`, `email`, `role` FROM ".table("users")." WHERE `id` = ?;", array($id));
  return(($result) ? $result->fetch_assoc() : null);
}

function getUsers() {
  $result = dbQuery("SELECT `id`, `first_name`, `last_name`, `email`, `role` FROM ".table("users")." ORDER BY `id`;");
  return(($result) ? $result->fetch_all(MYSQLI_ASSOC) : array());
}

//Email addresses and passwords are trimmed, to match what login() does
function createUser(){
  $firstName = trim($_POST['first_name']);
  $surName   = trim($_POST['surname']);
  $email     = trim($_POST['email']);
  $password  = trim($_POST['password']);
  $roles     = userRoles();
  $role      = (isset($_POST['role']) && isset($roles[$_POST['role']])) ? $_POST['role'] : null;

  if ($email == "" || $password == "") {
    printError(t("An email address and password are required"));
    return;
  }
  //Login needs each email address to belong to exactly one user
  if (loadUser($email) != null) {
    printError(t("A user with that email address already exists"));
    return;
  }

  $hashPassword = password_hash($password,PASSWORD_DEFAULT);

  $sql = "INSERT INTO ".table("users")." (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?);";
  reportSaved(dbQuery($sql, array($firstName, $surName, $email, $hashPassword, $role)), "User created");
}

//Change a user's name and email address from the admin user page
function updateUserDetails($user) {
  $first_name = trim($_POST['first_name']);
  $last_name  = trim($_POST['last_name']);
  $email      = trim($_POST['email']);

  if ($email == "") {
    printError(t("An email address is required"));
    return(FALSE);
  }
  $existing = loadUser($email);
  if ($existing != null && $existing["id"] != $user["id"]) {
    printError(t("A user with that email address already exists"));
    return(FALSE);
  }

  $sql = "UPDATE ".table("users")." SET `first_name` = ?, `last_name` = ?, `email` = ? WHERE `id` = ?;";
  $ok = reportSaved(dbQuery($sql, array($first_name, $last_name, $email, $user["id"])));
  //Keep an admin who changes their own email address logged in
  if ($ok && isset($_SESSION["user"]) && $_SESSION["user"] == $user["email"]) {
    $_SESSION["user"] = $email;
  }
  return($ok);
}

//Delete a user. User 1 and the logged in user can't be deleted.
function deleteUser($id) {
  $me = loadUser($_SESSION["user"]);
  if ($id == 1 || $id == $me["id"]) {
    printError(t("This user cannot be deleted"));
    return(FALSE);
  }
  $ok = dbQuery("DELETE FROM ".table("users")." WHERE `id` = ?;", array($id));
  if (!$ok) {
    printError(t("Could not delete").": ".dbError());
  }
  return((bool)$ok);
}

//Change another user's role from the user list. Returns a message for the page.
function setUserRole() {
  $roles = userRoles();
  $role = ($_POST['role'] == "") ? null : $_POST['role'];
  if ($role != null && !isset($roles[$role])) {
    return("Unknown role");
  }
  //Stops admins locking themselves out
  $me = loadUser($_SESSION["user"]);
  if ($me["id"] == $_POST['user_id']) {
    return("You cannot change your own role");
  }
  if (!dbQuery("UPDATE ".table("users")." SET `role` = ? WHERE `id` = ?;", array($role, $_POST['user_id']))) {
    return("Could not update role");
  }
  return("Role updated");
}

//Passwords are trimmed, to match what login() does
function editUser() {
  $error = "";

  $first_name  = trim($_POST['first_name']);
  $last_name   = trim($_POST['last_name']);
  $o_password  = trim($_POST['old_password']);
  $n_password1 = trim($_POST['new_password1']);
  $n_password2 = trim($_POST['new_password2']);

  if (!($o_password == "" && $n_password1 == "" && $n_password2 == "")) {
    if ($o_password == "") {
      $error .= "<p>Current password must be provided.</p>";
    } else {
      $rs = dbQuery("SELECT * FROM ".table("users")." WHERE `email` = ?;", array($_SESSION["user"]));
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
    if ($n_password1 === DEFAULT_PASSWORD) {
      $error .= "<p>Choose a password other than the default.</p>";
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

    $sql  = "UPDATE ".table("users")." SET `first_name` = ?, `last_name` = ?";
    $params = array($first_name, $last_name);
    if ($hashPassword != null) {
      $sql .= ", `password` = ?";
      $params[] = $hashPassword;
    }
    $sql .= " WHERE `email` = ?;";
    $params[] = $_SESSION["user"];
    if (reportSaved(dbQuery($sql, $params)) && $hashPassword != null) {
      unset($_SESSION["must_change_password"]);
    }
  }
}

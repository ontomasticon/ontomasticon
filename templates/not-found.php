<?php
//An address that no term has. It is sent as not found, but the page still lists the site's terms,
//as an old or mistyped link may be meant for one of them.
print "<div class='not-found'><p>".t("There is no term at this address. These are the site's terms.")."</p></div>";
template("home.php");

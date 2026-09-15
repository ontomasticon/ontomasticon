<?php
//Rules for crawlers: keep out of the administration and user pages, and find the site's pages in the sitemap
header("Content-Type: text/plain; charset=utf-8");
print "User-agent: *\n";
print "Disallow: ".sitePath("/admin/")."\n";
print "Disallow: ".sitePath("/user/")."\n";
print "\n";
print "Sitemap: ".siteURL()."sitemap.xml\n";

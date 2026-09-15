<?php
//The site's pages, for search engines: the home page, each vocabulary's page, and the page of each term outside a
//vocabulary. Terms in a vocabulary are on their vocabulary's page, at the fragment of their URI.
header("Content-Type: application/xml; charset=utf-8");
$urls = array(array(siteURL(), null));
foreach ($GLOBALS["ontomasticon"]["CVs"] as $CV) {
  $urls[] = array((new Vocabulary($CV["shortname"]))->uri(), null);
}
foreach (Term::inVocabulary(null) as $term) {
  $urls[] = array($term->uri(), jsonLDDate($term->modified));
}

print '<?xml version="1.0" encoding="UTF-8"?>'."\n";
print '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
foreach ($urls as list($url, $modified)) {
  $lastmod = ($modified === null) ? "" : "<lastmod>".h($modified["@value"])."</lastmod>";
  print "  <url><loc>".h($url)."</loc>".$lastmod."</url>\n";
}
print "</urlset>\n";

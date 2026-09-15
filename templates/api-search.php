<?php
//Terms whose name or short name contains ?q=, as a JSON array, for suggesting terms as a visitor types a search
header('Content-Type: application/json; charset=utf-8');
print(toJSON(termSuggestions(searchQuery())));
exit;

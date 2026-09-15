<div class="feature-container">
  <div class="feature">
    <h2><?php print("API Home"); ?></h2>

    <p>
        The API can be used to retrieve information about ontologies and terms in the
        Ontomasticon system. The API returns JSON formatted objects.
    </p>
  </div>

  <div class="feature">
    <h3>Terms endpoints</h3>
    <p>These endpoints return a term object.</p>

    <h4>/api/term/?term=</h4>
    <p>Given a term's URI returns a JSON term object. The URI must be URL-encoded.</p>

    <h4>/api/term/?shortname=</h4>
    <p>Given a term's short name returns a JSON term object.</p>

    <h4>&amp;format=jsonld or &amp;format=ttl</h4>
    <p>
        Add to either endpoint to return the term as a SKOS concept in JSON-LD or Turtle, including
        the properties TDWG requires of controlled vocabulary terms. A term's acronym is an alternative label.
    </p>
    <p>
        On a site that is a glossary, the term is followed by its words as OntoLex lexical entries: its name
        at the term's URI with <code>#entry</code> added (or <code>:entry</code> when the URI already has a
        fragment), and its acronym at <code>#acronym</code> (or <code>:acronym</code>). They give their LexInfo
        term type, and whether the word is the preferred term or, for a synonym, an admitted one.
    </p>
  </div>

  <div class="feature">
    <h3>Search endpoint</h3>

    <h4>/api/search/?q=</h4>
    <p>
        Returns a JSON array of up to ten terms whose name, short name or acronym contains the text, those that start
        with it first, as the site's search box suggests them. Each has its <code>name</code>,
        <code>shortname</code>, <code>acronym</code> (or null) and <code>uri</code>, and the name of its <code>vocabulary</code> (or null).
        Each also has <code>synonym_of</code>: for a synonym, the name of the term it is a synonym of, whose
        <code>uri</code> and <code>vocabulary</code> are given; otherwise null.
    </p>
  </div>

  <div class="feature">
    <h3>Vocabulary endpoints</h3>
    <p>These endpoints return JSON-LD, or Turtle when <code>format=ttl</code> is added.</p>

    <h4>/api/cv/?shortname=</h4>
    <p>
        Given a controlled vocabulary's short name returns the vocabulary as a SKOS concept
        scheme, followed by all of its terms as SKOS concepts.
    </p>

    <h4>/api/cv/</h4>
    <p>Returns the terms that aren't in a controlled vocabulary, as the site's own concept scheme.</p>
  </div>

  <div class="feature">
    <h3>Linked data</h3>
    <p>
        The site's own addresses return the same RDF to clients that ask for it with the header
        <code>Accept: application/ld+json</code> or <code>Accept: text/turtle</code>, or with
        <code>?format=jsonld</code> or <code>?format=ttl</code>: the site's address returns the site's
        concept scheme, <code>/cv/</code> followed by a short name returns that vocabulary's scheme,
        and the address of a term that isn't in a vocabulary returns the term. Other clients,
        including browsers, get the HTML page.
    </p>
  </div>
</div>

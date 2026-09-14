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
    <p>Given a term URL returns a JSON term object. The term must be HTML encoded.</p>

    <h4>/api/term/?shortname=</h4>
    <p>Given a term name returns a JSON term object.</p>

    <h4>&amp;format=jsonld</h4>
    <p>
        Add to either endpoint to return the term as a SKOS concept in JSON-LD, including the
        properties TDWG requires of controlled vocabulary terms.
    </p>
  </div>

  <div class="feature">
    <h3>Vocabulary endpoints</h3>
    <p>These endpoints return JSON-LD.</p>

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
        The site's own addresses return the same JSON-LD to clients that ask for it with the header
        <code>Accept: application/ld+json</code>, or with <code>?format=jsonld</code>: the site's address
        returns the site's concept scheme, <code>/cv/</code> followed by a short name returns that
        vocabulary's scheme, and the address of a term that isn't in a vocabulary returns the term.
        Other clients, including browsers, get the HTML page.
    </p>
  </div>
</div>
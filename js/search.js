// Ontomasticon: suggests terms as a visitor types in the search box in the page header.
// Without this script the search box still works, opening the page of search results.
(function () {
  "use strict";

  var form = document.getElementById("term-search");
  var input = document.getElementById("term-search-input");
  if (!form || !input || !window.fetch) {
    return;
  }

  var endpoint = form.getAttribute("data-suggestions");
  var list = document.createElement("ul");
  list.id = "term-search-suggestions";
  list.className = "term-search-suggestions";
  list.setAttribute("role", "listbox");
  list.hidden = true;
  form.appendChild(list);

  input.setAttribute("role", "combobox");
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-controls", list.id);
  input.setAttribute("aria-expanded", "false");

  var suggestions = [];
  var active = -1;
  var timer = null;
  //Only the answer to the latest request is shown, as answers can arrive out of order
  var latest = 0;

  function close() {
    list.hidden = true;
    list.innerHTML = "";
    suggestions = [];
    active = -1;
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
  }

  function highlight(index) {
    var items = list.children;
    if (active >= 0 && items[active]) {
      items[active].setAttribute("aria-selected", "false");
    }
    active = index;
    if (active >= 0 && items[active]) {
      items[active].setAttribute("aria-selected", "true");
      input.setAttribute("aria-activedescendant", items[active].id);
      items[active].scrollIntoView({block: "nearest"});
    } else {
      input.removeAttribute("aria-activedescendant");
    }
  }

  //A term in a vocabulary may be on the page already open, when only the address's fragment changes, so the list is closed first
  function go(index) {
    var uri = suggestions[index].uri;
    close();
    window.location.href = uri;
  }

  function show(terms) {
    close();
    if (terms.length === 0) {
      return;
    }
    suggestions = terms;
    terms.forEach(function (term, index) {
      var item = document.createElement("li");
      item.id = "term-search-suggestion-" + index;
      item.setAttribute("role", "option");
      item.setAttribute("aria-selected", "false");

      var name = document.createElement("span");
      name.className = "term-search-name";
      name.textContent = term.name;
      item.appendChild(name);

      var details = [];
      if (term.synonym_of) {
        details.push(form.getAttribute("data-synonym-of") + " " + term.synonym_of);
      }
      if (term.vocabulary) {
        details.push(term.vocabulary);
      }
      if (details.length > 0) {
        var detail = document.createElement("span");
        detail.className = "term-search-detail";
        detail.textContent = details.join(" · ");
        item.appendChild(detail);
      }

      //Keep focus in the search box, so choosing with the mouse doesn't close the list first
      item.addEventListener("mousedown", function (event) {
        event.preventDefault();
      });
      item.addEventListener("click", function () {
        go(index);
      });
      list.appendChild(item);
    });
    list.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  input.addEventListener("input", function () {
    clearTimeout(timer);
    var query = input.value.trim();
    if (query.length < 2) {
      latest++;
      close();
      return;
    }
    timer = setTimeout(function () {
      var request = ++latest;
      fetch(endpoint + "?q=" + encodeURIComponent(query))
        .then(function (response) {
          return response.ok ? response.json() : [];
        })
        .then(function (terms) {
          if (request === latest && Array.isArray(terms)) {
            show(terms);
          }
        })
        .catch(function () {
          if (request === latest) {
            close();
          }
        });
    }, 150);
  });

  input.addEventListener("keydown", function (event) {
    if (list.hidden || suggestions.length === 0) {
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      highlight((active + 1) % suggestions.length);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      highlight((active <= 0) ? suggestions.length - 1 : active - 1);
    } else if (event.key === "Enter" && active >= 0) {
      //Enter without a highlighted suggestion submits the form, for the page of results
      event.preventDefault();
      go(active);
    } else if (event.key === "Escape") {
      close();
    }
  });

  input.addEventListener("blur", close);
})();

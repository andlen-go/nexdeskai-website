(function () {
  var form = document.getElementById('contact-form');
  if (!form) { return; }

  var select = form.elements.interest;
  var status = document.getElementById('contact-status');
  var button = form.querySelector('button[type="submit"]');
  var done = document.getElementById('contact-done');

  var links = document.querySelectorAll('a[data-interest]');
  Array.prototype.forEach.call(links, function (link) {
    link.addEventListener('click', function () {
      select.value = link.getAttribute('data-interest');
      window.setTimeout(function () {
        try { form.elements.name.focus({ preventScroll: true }); } catch (e) { form.elements.name.focus(); }
      }, 500);
    });
  });

  if (!window.fetch || !window.FormData) { return; }

  function show(message, isError) {
    status.textContent = message;
    status.hidden = false;
    status.className = 'cf-status' + (isError ? ' is-error' : '');
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    button.disabled = true;
    show('Sending\u2026', false);

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json' }
    }).then(function (response) {
      return response.json().then(function (data) {
        return { status: response.status, data: data };
      });
    }).then(function (result) {
      if (result.data && result.data.ok) {
        form.hidden = true;
        done.hidden = false;
        done.focus();
        return;
      }
      var text = 'We could not send your request. Please try again or email admin@mugenai.com.';
      if (result.data && result.data.errors) {
        text = Object.keys(result.data.errors).map(function (key) { return result.data.errors[key]; }).join(' ');
      } else if (result.data && result.data.message) {
        text = result.data.message;
      }
      show(text, true);
      button.disabled = false;
    }).catch(function () {
      show('We could not send your request. Please try again or email admin@mugenai.com.', true);
      button.disabled = false;
    });
  });
}());

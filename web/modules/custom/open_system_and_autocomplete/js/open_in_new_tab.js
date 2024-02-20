document.addEventListener('DOMContentLoaded', function () {
  var button = document.querySelector('.open-in-new-tab-button');
  if (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      fetch('/run_rpa_for_a_system')
        .then(response => response.json())
        .then(data => console.log(data))
        .catch(error => console.error('Error:', error));
      window.open('https://www.google.com', '_blank');
    });
  }
});
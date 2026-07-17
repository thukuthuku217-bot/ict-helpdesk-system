// ICT Help Desk — main.js

function getCsrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(function() { el.remove(); }, 500);
    }, 5000);
});

var toggleBtn = document.getElementById('sidebar-toggle');
var sidebar   = document.getElementById('sidebar');
if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function(e) {
        if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
            sidebar.classList.remove('open');
        }
    });
}

function togglePw(id, btn) {
    var f = document.getElementById(id);
    if (!f) return;
    if (f.type === 'password') { f.type = 'text'; btn.textContent = '🙈'; }
    else { f.type = 'password'; btn.textContent = '👁'; }
}

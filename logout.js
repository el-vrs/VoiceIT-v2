document.addEventListener('DOMContentLoaded', function() {
  const logoutBtn = document.getElementById('logoutBtn');
  const logoutModal = document.getElementById('logoutModal');
  const confirmLogout = document.getElementById('confirmLogout');
  const cancelLogout = document.getElementById('cancelLogout');

  logoutBtn.addEventListener('click', function(e) {
    e.preventDefault();
    logoutModal.style.display = 'flex';
  });

  confirmLogout.addEventListener('click', function() {
    localStorage.clear();
    sessionStorage.clear();
    
    window.location.href = 'logout.php'; 
  });

  cancelLogout.addEventListener('click', function() {
    logoutModal.style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    if (event.target === logoutModal) {
      logoutModal.style.display = 'none';
    }
  });
});
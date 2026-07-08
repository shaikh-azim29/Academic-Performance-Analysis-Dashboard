    </main>
  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Mobile sidebar overlay -->
<div class="modal-overlay" id="sidebarOverlay"></div>

<script>
// Sidebar toggle
const sidebar   = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
const overlay   = document.getElementById('sidebarOverlay');

function toggleSidebar(open) {
  sidebar.classList.toggle('open', open);
  overlay.classList.toggle('open', open);
}

hamburger?.addEventListener('click', () => toggleSidebar(!sidebar.classList.contains('open')));
overlay?.addEventListener('click', () => toggleSidebar(false));

// Auto-dismiss alerts
document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
  setTimeout(() => el.remove(), 4000);
});
</script>
</body>
</html>

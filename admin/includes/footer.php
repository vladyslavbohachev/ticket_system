</body>
    <script>
        // Dropdown Menu Funktionen
        document.addEventListener('DOMContentLoaded', function() {
            const profileToggle = document.getElementById('profileToggle');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownOverlay = document.getElementById('dropdownOverlay');

            function toggleDropdown() {
                const isActive = dropdownMenu.classList.contains('active');
                dropdownMenu.classList.toggle('active', !isActive);
                dropdownOverlay.style.display = isActive ? 'none' : 'block';
            }

            function closeDropdown() {
                dropdownMenu.classList.remove('active');
                dropdownOverlay.style.display = 'none';
            }

            profileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleDropdown();
            });

            dropdownOverlay.addEventListener('click', closeDropdown);

            // Schließe Dropdown bei Klick außerhalb
            document.addEventListener('click', function(e) {
                if (!dropdownMenu.contains(e.target) && !profileToggle.contains(e.target)) {
                    closeDropdown();
                }
            });

            // Schließe Dropdown bei ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDropdown();
                }
            });

            // Hover-Effekt für Profil-Circle
            profileToggle.addEventListener('mouseenter', function() {
                if (!dropdownMenu.classList.contains('active')) {
                    this.style.transform = 'scale(1.05)';
                }
            });

            profileToggle.addEventListener('mouseleave', function() {
                if (!dropdownMenu.classList.contains('active')) {
                    this.style.transform = 'scale(1)';
                }
            });
        });
    </script>
    </html>
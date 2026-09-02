 <?php
// includes/student_footer.php -
?>
    </div> <!-- closing content div -->
    </div> <!-- closing wrapper div -->
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar scroll effect
        $(window).scroll(function() {
            if ($(this).scrollTop() > 50) {
                $('.navbar').addClass('scrolled');
            } else {
                $('.navbar').removeClass('scrolled');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { 
                    if(alert.parentNode) alert.remove(); 
                }, 500);
            });
        }, 5000);
        
        // Confirm delete
        function confirmDelete(message) {
            return confirm(message || 'هل أنت متأكد من الحذف؟');
        }
        
        // Toggle sidebar on mobile
        $('.menu-toggle').on('click', function() {
            $('.sidebar').toggleClass('active');
        });
        
        // Close sidebar when clicking outside on mobile
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.sidebar').length && !$(event.target).closest('.menu-toggle').length) {
                $('.sidebar').removeClass('active');
            }
        });
    </script>
</body>
</html>
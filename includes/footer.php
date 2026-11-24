    </div> <!-- End of p-6 / p-8 -->
    <!-- Global Footer -->
    <footer class="mt-auto border-t border-brand-border/40 py-6 bg-brand-surface/20">
        <div class="max-w-7xl mx-auto px-6 text-brand-muted">
            <?php echo str_replace(['__YEAR__', '__SITE__'], [date('Y'), SITE_NAME . ' ' . SITE_SUBTITLE], '<div class="flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-brand-muted w-full"><p class="text-center md:text-left">&copy; __YEAR__ __SITE__. All rights reserved.</p><p class="text-center">Abhinav Gaming</p></div>'); ?>
        </div>
    </footer>
</main> <!-- End of flex-1 / main -->

<!-- Script for Mobile Navigation Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const nav = document.getElementById('sidebar-nav');
    
    if (toggleBtn && nav) {
        toggleBtn.addEventListener('click', function() {
            nav.classList.toggle('hidden');
            nav.classList.toggle('flex');
        });
    }
});

// Helper for Copy to Clipboard UI animations
function copyToClipboard(text, buttonElement) {
    navigator.clipboard.writeText(text).then(function() {
        const originalHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fa-solid fa-check text-brand-success mr-1"></i> Copied!';
        buttonElement.classList.add('bg-brand-success/10', 'text-brand-success', 'border-brand-success/20');
        
        setTimeout(function() {
            buttonElement.innerHTML = originalHtml;
            buttonElement.classList.remove('bg-brand-success/10', 'text-brand-success', 'border-brand-success/20');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>
</body>
</html>

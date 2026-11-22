<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

if ($_POST) {

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = 'Mod name is required';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO mods (name, description) VALUES (?, ?)");
        
        if ($stmt->execute([$name, $description])) {
            $success = 'Mod added successfully!';
            $name = $description = ''; // Clear form
        } else {
            $error = 'Failed to add mod. Please try again.';
        }
    }
}

$page_title = "Add Mod - " . SITE_NAME . " " . SITE_SUBTITLE;
require_once 'includes/header.php';
?>

<!-- Header -->
<div class="mb-8 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-glow-primary">Add Mod Name</h2>
        <p class="text-sm text-brand-muted mt-1 font-medium">Create a new mod entry in the system.</p>
    </div>
    <a href="mods" class="inline-flex items-center justify-center px-3.5 py-2 bg-brand-surface border border-brand-border text-xs font-semibold rounded-xl hover:text-brand-primary hover:border-brand-primary/40 transition-colors space-x-1.5">
        <i class="fa-solid fa-arrow-left-long"></i>
        <span>Back to Mods</span>
    </a>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="bg-brand-success/10 border border-brand-success/20 text-brand-success text-xs rounded-xl p-3.5 mb-6 flex items-center space-x-2.5">
        <i class="fa-solid fa-circle-check text-base"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-brand-error/10 border border-brand-error/20 text-brand-error text-xs rounded-xl p-3.5 mb-6 flex items-center space-x-2.5">
        <i class="fa-solid fa-circle-exclamation text-base"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Form Card -->
<div class="max-w-xl glass-card rounded-2xl p-6 border border-brand-border/60">
    <h3 class="text-base font-bold mb-4 flex items-center space-x-2">
        <i class="fa-solid fa-plus text-brand-primary"></i>
        <span>Mod Details</span>
    </h3>
    
    <form method="POST" class="space-y-4">
        <div>
            <label for="name" class="block text-xs font-semibold text-brand-muted mb-1.5">Mod Name *</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo htmlspecialchars($name ?? ''); ?>"
                   class="w-full px-4 py-2.5 bg-brand-bg/50 border border-brand-border rounded-xl text-sm focus:outline-none focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-all duration-300 placeholder-brand-muted/40"
                   placeholder="e.g. KING MOD APK">
        </div>

        <div>
            <label for="description" class="block text-xs font-semibold text-brand-muted mb-1.5">Description</label>
            <textarea id="description" name="description" rows="4"
                      class="w-full px-4 py-2.5 bg-brand-bg/50 border border-brand-border rounded-xl text-sm focus:outline-none focus:border-brand-primary focus:ring-1 focus:ring-brand-primary transition-all duration-300 placeholder-brand-muted/40"
                      placeholder="Enter mod description or version information..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
        </div>

        <button type="submit" class="w-full py-3 bg-gradient-to-r from-brand-primary to-brand-secondary hover:opacity-90 active:scale-[0.98] transition-all duration-300 rounded-xl text-sm font-bold text-white shadow-glow flex items-center justify-center space-x-2">
            <i class="fa-solid fa-square-plus"></i>
            <span>Add Mod</span>
        </button>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
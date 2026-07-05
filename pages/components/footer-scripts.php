<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>

<script>
    // --- GLOBAL LOADING SCREEN LOGIC ---
    document.addEventListener("DOMContentLoaded", function() {
        const loader = document.getElementById("global-loader");

        // 1. Hide loader when page is ready (with small buffer)
        if (loader) {
            setTimeout(() => {
                loader.classList.add("loader-hidden");
            }, 200); 
        }

        // 2. Show loader on link click (Transition Effect)
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const target = this.getAttribute('target');

                // Valid link checks
                if (
                    href && 
                    !href.startsWith('#') && 
                    !href.startsWith('javascript') && 
                    target !== '_blank' &&
                    !e.ctrlKey && !e.metaKey
                ) {
                    if(loader) loader.classList.remove("loader-hidden");
                }
            });
        });

        // 3. Show loader on Form Submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                if(loader) loader.classList.remove("loader-hidden");
            });
        });
    });

    // Fallback: Force hide if page is restored from cache (Back button)
    window.addEventListener("pageshow", function(event) {
        if (event.persisted) {
            const loader = document.getElementById("global-loader");
            if (loader) loader.classList.add("loader-hidden");
        }
    });
</script>
/* Artflow Studio Tenancy Package - JavaScript */
(function() {
    'use strict';
    
    window.ArtflowTenancy = {
        version: '1.0.0',
        
        init: function() {
            console.log('Artflow Tenancy package loaded');
        },
        
        utils: {
            formatTenantId: function(id) {
                return id.substring(0, 8) + '...';
            }
        }
    };
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.ArtflowTenancy.init);
    } else {
        window.ArtflowTenancy.init();
    }
})();

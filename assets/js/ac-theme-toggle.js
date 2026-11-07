// Alternador de tema claro/escuro — partilhado entre a Área de Cliente
// e o Backoffice (ambos têm o botão #ac-theme-toggle no topbar).
( function () {
	var btn = document.getElementById( 'ac-theme-toggle' );
	if ( ! btn ) return;
	btn.addEventListener( 'click', function () {
		var root    = document.documentElement;
		var current = root.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light';
		var next    = current === 'dark' ? 'light' : 'dark';
		root.setAttribute( 'data-theme', next );
		btn.setAttribute( 'aria-pressed', next === 'dark' ? 'true' : 'false' );
		try { localStorage.setItem( 'cp-theme', next ); } catch ( e ) {}
	} );
} )();

const loginError = document.getElementById('login-error');

function reset_submit(e) {
    e.preventDefault();

    loginError.textContent = 'Please wait...';

    const fd = new FormData(e.srcElement);
    fd.append('func', 'forgot');

    const xhttp = new XMLHttpRequest();
        xhttp.open('POST', installUrl+'reset_password.php', true);
        xhttp.send(fd);
    
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            const result = this.responseText;
            if (result == 'success') {
                window.location.replace(homeUrl+'admin.php');
            } else {
                shake(loginContainer);
                loginError.textContent = 'Failed...';
            }
        }
    }
}

function init() {
	document.addEventListener('keydown', (ele) => {
	// Enter key attempts login
		if (ele.keyCode == 13) {
			document.getElementById('reset-button').click();
		}
    });

}

init();

const loginContainer = document.getElementById('login-container'),
    loginError = document.getElementById('login-error');
    urlParams = new URLSearchParams(window.location.search);
    source = urlParams.get('source');

function form_submit(e) {
    e.preventDefault();

    const fd = new FormData(e.srcElement);

    const xhttp = new XMLHttpRequest();
        xhttp.open('POST', installUrl+'login.php'+((source) ? '?source='+source : ''), true);
        xhttp.send(fd);
    
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            console.log(this.responseText);
            const result = this.responseText;
            if (result == 'success') {
                loginError.textContent = 'Logging In!';
                window.location.replace(homeUrl+((source) ? source : ''));
            } else {
                shake(loginContainer);
                switch (this.responseText) {
                    case ('badCred'):
                        loginError.textContent = 'Incorrect Login.';
                        break;
                    default:
                        loginError.textContent = 'Error. Try again';
                        break;
                }
            }
        }
    }
}

function shake(ele) {
    ele.classList.add('shake');
    ele.addEventListener('animationend', () => ele.classList.remove('shake'));
    ele.addEventListener('WebkitAnimationEnd', () => ele.classList.remove('shake'));
}

function init() {
	document.addEventListener('keydown', (ele) => {
	// Enter key attempts login
		if (ele.keyCode == 13) {
			document.getElementById('login-button').click();
		}
	});
}

init();

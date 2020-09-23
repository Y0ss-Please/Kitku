const loginContainer = document.getElementById('login-container'),
    loginError = document.getElementById('login-error'),
    forgotLink = document.getElementById('forgot-password'),
    forgotBox = document.getElementById('forgot-box'),
    loginBox = document.getElementById('login-box'),
    urlParams = new URLSearchParams(window.location.search),
    source = urlParams.get('source');

function login_submit(e) {
    e.preventDefault();

    const fd = new FormData(e.srcElement);
    fd.append('func', 'login');

    const xhttp = new XMLHttpRequest();
        xhttp.open('POST', installUrl+'login.php'+((source) ? '?source='+source : ''), true);
        xhttp.send(fd);
    
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            result = this.responseText;
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

function forgot_submit(e) {
    e.preventDefault();

    loginError.textContent = 'Please wait...';

    const fd = new FormData(e.srcElement);
    fd.append('func', 'forgot');

    const xhttp = new XMLHttpRequest();
        xhttp.open('POST', installUrl+'login.php', true);
        xhttp.send(fd);
    
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            const result = this.responseText;
            console.log(result);
            if (result == 'sent') {
                loginError.textContent = 'E-mail sent!';
            } else {
                shake(loginContainer);
                switch (this.responseText) {
                    case ('failed'):
                        loginError.textContent = 'Unable to send.';
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
    
    forgotLink.addEventListener('click', ()=> {
        loginError.innerHTML = '';
        loginBox.classList.add('hidden');
        forgotLink.classList.add('hidden');
        document.getElementById('login-button').classList.add('hidden')
        forgotBox.classList.remove('hidden');
        document.getElementById('forgot-button').classList.remove('hidden');
    });
}

init();

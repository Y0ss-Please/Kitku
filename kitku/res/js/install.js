const containerBody = document.querySelector('.content-body'),
nextButton = document.getElementById('next-button'),
backButton = document.getElementById('back-button'),
buttons = document.querySelectorAll('.button'),
paginatePages = document.querySelectorAll('.paginate-page'),
paginateButtons = document.querySelectorAll('.paginate-button'),
paginateCount = paginatePages.length,
pageMessage = document.getElementById('message');

function paginate(num) {

	setVisibility(paginatePages);
	setVisibility(paginateButtons);

	if (num == 'msg') {
		buttons.forEach((e)=>e.classList.add('hidden'));
		pageMessage.classList.remove('hidden');
	} else {
		pageMessage.classList.add('hidden');
	}

	function setVisibility(elements) {
		elements.forEach( function(element) {
			if (element.classList.contains('page'+num)) {
				element.classList.remove('hidden');
			} else {
				element.classList.add('hidden');
			}
		});
	}
}

function formSubmit(e, page) {
	e.preventDefault();

	paginate('msg');

	let count = 0;
	const interval = setInterval(function() {
		pageMessage.textContent.includes('...') ? pageMessage.textContent = 'Working on it' : pageMessage.textContent += '.';
		count++;
		if (count > 20) {
			clearInterval(interval);
			pageMessage.firstChild.textContent = 'This is taking far longer than it should. Check your internet connection, or try restarting your browser.';
		}
	}, 500);

	const fd = new FormData(e.srcElement);
	fd.append('page', page);

	const xhttp = new XMLHttpRequest();
		xhttp.open('POST', `${installUrl}install.php`, true);
		xhttp.send(fd);

	xhttp.onreadystatechange = function() {
			if (this.readyState == 4 && this.status == 200){
				clearInterval(interval);
				console.log(this.responseText);
				if (this.responseText.includes('success')) {
					activePage++;
					paginate(activePage);	
				} else if (this.responseText.includes('goHome')){
					window.location.replace(homeUrl+'admin');
				} else {
					if (this.responseText.includes('noHost')) {
						resetPage('Host not found. Check your server name and try again.');
					} else if (this.responseText.includes('badCred')) {
						resetPage('Bad credentials. Confirm your username and password are correct.');
					} else if (this.responseText.includes('serveErr')){
						resetPage('Server Error. Please try again.');
					} else if (this.responseText.includes('createDBFail')){
						resetPage("Can't create database. Check your database priveledges.");
					} else {
						resetPage('There was an unknown error, please contact the Kitku team.');
					}
				}
			}
		}

	function resetPage(msg) {
		clearInterval(interval);
		pageMessage.textContent = msg;
		backButton.classList.remove('hidden');
		backButton.addEventListener('click', function _listener() {
			paginate(activePage);
			pageMessage.textContent = 'Working on it';
			backButton.classList.add('hidden');
			backButton.removeEventListener("click", _listener, true);
		}, true);
	}    
}

function init() {
	paginate(activePage);
	document.addEventListener('keydown', (ele) => {
	// Enter key progresses page
		if (ele.keyCode == 13) {
			buttons.forEach( (e)=> {
				if (!e.classList.contains('hidden')) {
					e.click();
				}
			})
		}
	});
}

init();
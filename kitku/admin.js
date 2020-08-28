const navbarItems = document.querySelectorAll('.navbar-item');
const mainContents = document.querySelectorAll('.main-content');

navbarItems.forEach(element => {
    element.addEventListener('click', (event)=>{
        set_active_page(event.currentTarget.getAttribute('data-page'));
    });
});

function set_active_page(page) {
    items = [navbarItems, mainContents];
    items.forEach(item => {
        item.forEach(element => {
            if (element.getAttribute('data-page') == page) {
                element.classList.add('active');
            } else {
                element.classList.remove('active');
            }
        });
    });    
    populate_page(page);
}

function populate_page(page) {

    const xhttp = new XMLHttpRequest();
        xhttp.open('POST', installUrl+'admin.php?request=true', true);
        xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhttp.send(`page=${page}`);

    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            result = JSON.parse(this.responseText);
            switch(page) {
                case 'posts':
                    build_table(document.getElementById('posts-table'));
                break;
                case 'pages':
                    build_table(document.getElementById('pages-table'));
                break;
            }
        }
    }

    function build_table(target) {
        tableHead = target.querySelector('thead');
        tableBody = target.querySelector('tbody');

        fragment = document.createDocumentFragment();

        tableHead.innerHTML = '';
        tableBody.innerHTML = '';

        // Build table header
        tr = document.createElement('tr');
        for(val in result[0]) {
            if (val != 'id' && val != 'content'){
                th = document.createElement('th');
                th.textContent = val.charAt(0).toUpperCase() + val.slice(1);
                tr.appendChild(th);
            }
        }
        for (i = 0; i < 2; i++ ) { // Two blank th elements for edit and delete columns.
            th = document.createElement('th');
            tr.appendChild(th);
        }
        fragment.appendChild(tr);
        tableHead.appendChild(fragment);

        // fill table body with entries from result
        result.forEach( (e)=> {
            tr = document.createElement('tr');
            for(val in e) {
                if (val != 'id' && val != 'content'){
                    td = document.createElement('td');
                    td.textContent = e[val];
                    tr.appendChild(td);
                }
            }
            td = document.createElement('td');
            td.appendChild(insert_icon('edit'));
            tr.appendChild(td);

            td = document.createElement('td');
            td.appendChild(insert_icon('delete'));
            tr.appendChild(td);
            fragment.appendChild(tr);
        });
        tableBody.appendChild(fragment);
    }
}

function insert_icon(icon) {
    const element = document.querySelector('.icon-'+icon).cloneNode(true);
        element.classList.remove('hidden');
    return element;
}
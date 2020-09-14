import * as name from "./quill.js";

const newPost = new Quill('#post-editor', {
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'align': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'script': 'sub'}, { 'script': 'super' }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [ 'link', 'image', 'video' ],
        ]
    },
    placeholder: 'Compose an epic...',
    theme: 'snow'
});

const mainContents = document.querySelectorAll('.main-content'),
    navbarItems = document.querySelectorAll('.navbar-item'),
    newButtons = document.querySelectorAll('.new-button'),
    newPostButton = document.getElementById('new-post-button'),
    newPostSubmit = document.getElementById('new-post-submit'),
    logoutButton = document.getElementById('logout-button');

const pageChangers = [navbarItems, newButtons];

function build_listners() {
    pageChangers.forEach(element => {
        set_page_change_listeners(element);
    });

    logoutButton.addEventListener('click', logout);

    newPostButton.addEventListener('click', () => {
        newPostSubmit.click()
        new_post();
    });

    function set_page_change_listeners(list) {
        list.forEach(element => {
            element.addEventListener('click', (event)=>{
                change_page(event.currentTarget.getAttribute('data-page'));
            });
        });
    }
}

function change_page(page) {   

    set_active_attributes(page);
    populate_active_page(page);

    function set_active_attributes(page) {
        const items = [navbarItems, mainContents];
        items.forEach(item => {
            item.forEach(element => {
                if (element.getAttribute('data-page') == page || element.getAttribute('data-children') == page) {
                    element.classList.add('active');
                } else {
                    element.classList.remove('active');
                }
            });
        }); 
    }

    function populate_active_page(page) {

        var result;

        if (page == 'posts' || page == 'pages') {
    
            const xhttp = new XMLHttpRequest();
                xhttp.open('POST', installUrl+'admin.php', true);
                xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhttp.send(`func=get_data&page=${page}`);
    
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
                let tableHead = target.querySelector('thead');
                let tableBody = target.querySelector('tbody');
    
                let fragment = document.createDocumentFragment();
    
                tableHead.innerHTML = '';
                tableBody.innerHTML = '';
    
                // Build table header
                let tr = document.createElement('tr');
                for(let val in result[0]) {
                    if (!buildTableIgnores.includes(val)){
                        const th = document.createElement('th');
                        th.textContent = val.charAt(0).toUpperCase() + val.slice(1);
                        tr.appendChild(th);
                    }
                }
                for (let i = 0; i < 2; i++ ) { // Two blank th elements for edit and delete columns.
                    const th = document.createElement('th');
                    tr.appendChild(th);
                }
                fragment.appendChild(tr);
                tableHead.appendChild(fragment);
    
                // fill table body with entries from result
                result.forEach( (e)=> {
                    tr = document.createElement('tr');
                    let first = true;
                    for(const val in e) {
                        if (!buildTableIgnores.includes(val)){
                            const td = document.createElement('td');
                            td.textContent = e[val];
                            tr.appendChild(td);
                        }
                    }
                    let td = document.createElement('td');
                    td.appendChild(insert_icon('edit'));
                    tr.appendChild(td);
    
                    td = document.createElement('td');
                    td.appendChild(insert_icon('delete'));
                    tr.appendChild(td);
                    fragment.appendChild(tr);
                });
                tableBody.appendChild(fragment);
            }

            function insert_icon(icon) {
                const element = document.querySelector('.icon-'+icon).cloneNode(true);
                    element.classList.remove('hidden');
                return element;
            }
        }
    }
}

function new_post() {
    
    const form = document.forms['new-post'];
    if (!form['new-post-title'].value) return false ;

    const fd = new FormData(form);
    fd.append('new-post-content', newPost.root.innerHTML);
    fd.append('func', 'new_post')

    const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.send(fd);

    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            const result = this.responseText;
            console.log(result);
            if (result == 'success') {
                console.log('success')
            } else if (result == 'titleTaken') {
                console.log('fail');
            }
        }
    }
}

function logout() {
    const xhttp = new XMLHttpRequest();
                xhttp.open('POST', installUrl+'admin.php', true);
                xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhttp.send('func=logout');
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            const result = this.responseText;
            if (result == 'failed') {
                alert('There was an error connecting to the server. Check your internet connection and try again.');
            }
            location.reload();
        }
    }
}

function init() {

    build_listners();

    const newPage = new Quill('#page-editor', {
        modules: {
            toolbar: [
            [{ header: [1, 2, false] }],
            ['bold', 'italic', 'underline'],
            ['image', 'code-block']
            ]
        },
        placeholder: 'Compose an epic...',
        theme: 'snow'
    });
}

init();
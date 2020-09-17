import * as name from "./quill.js";

var tags = [];
var categories = [];

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
        } else if (page == 'new-post') {
            const xhttp = new XMLHttpRequest();
                xhttp.open('POST', installUrl+'admin.php', true);
                xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhttp.send(`func=get_data&page=${page}`);
    
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200){
                    result = JSON.parse(this.responseText);
                    tags = result[0];
                    categories = result[1];

                    tags = tags.slice(0, 10);
                    const tagsElement = document.getElementById('new-post-tags');
                    const tagsContainer = document.getElementById('new-post-tags-container');
                    let fragment = document.createDocumentFragment();
                    tags.forEach( (tag) => {
                        const newTag = document.createElement('div');
                        newTag.classList.add('add-tag');
                        newTag.textContent = '+'+tag;
                        fragment.appendChild(newTag);

                        newTag.addEventListener('click', (e) => {
                            if (tagsElement.value) {
                                tagsElement.value += ', ';
                            }
                            tagsElement.value += e.target.textContent.substring(1);
                        });
                    });

                    tagsContainer.appendChild(fragment);

                    fragment = document.createDocumentFragment();
                    const categoryElement = document.getElementById('new-post-category')
                    const categoryDropdown = document.getElementById('category-dropdown');

                    categoryElement.addEventListener('focus', (e) => {
                        e.stopPropagation();
                        populate_category_dropdown();
                        categoryElement.addEventListener('keyup', key_refresh);
                    });

                    const key_refresh = function(e) {
                        populate_category_dropdown();
                    }

                    const close = function(e) {
                        categoryDropdown.classList.add('hidden');
                        categoryElement.removeEventListener('focusout', close);
                        categoryElement.removeEventListener('keyup', key_refresh);
                    }
                    categoryElement.addEventListener('focusout', close);
                    
                    function populate_category_dropdown() {
                        categoryDropdown.classList.remove('hidden');

                        if (categories) {
                            categoryDropdown.innerHTML = '';
                            categories.forEach((category) => {
                                let match = true;

                                if (categoryElement.value) {
                                    for(let i=0; i<categoryElement.value.length; i++) {
                                        if (category.charAt(i).toLowerCase() != categoryElement.value.charAt(i).toLowerCase()) {
                                            match = false;
                                            i = categoryElement.value.length;
                                        } else {
                                            match = true;
                                        }
                                    }
                                }

                                if (match) {
                                    const newCategory = document.createElement('div');
                                    newCategory.textContent = category;
                                    fragment.appendChild(newCategory);
        
                                    newCategory.addEventListener('click', (e) => {
                                        categoryElement.value = e.target.textContent;
                                    });
                                }
                            });

                            categoryDropdown.appendChild(fragment);
                        }
                    }
                }
            }
        }
    }
}

function new_post() {

    
    const form = document.forms['new-post'];
    if (!form['new-post-title'].value) return false ;

    const fd = new FormData(form);
    fd.append('func', 'new_post')

    const imageElements = newPost.root.querySelectorAll('img');

    let i = 0;
    const images = {};
    imageElements.forEach(element => {
        images['image-'+i] = element.src;
        element.src = 'image_'+i;
        i++;
    });

    fd.append('images', JSON.stringify(images));
    fd.append('new-post-content', newPost.root.innerHTML);

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

    i = 0;
    imageElements.forEach(element => {
        element.src = images['image-'+i];
        i++;
    });
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
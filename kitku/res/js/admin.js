import * as name from "./quill.js";

var tags = [];
var categories = [];

const mainContents = document.querySelectorAll('.main-content'),
    navbarItems = document.querySelectorAll('.navbar-item'),
    newButtons = document.querySelectorAll('.new-button'),
    editorButton = document.getElementById('editor-button'),
    editorSubmit = document.getElementById('editor-submit'),
    logoutButton = document.getElementById('logout-button');

const pageChangers = [navbarItems, newButtons];

const quillOptions = {
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
    theme: 'snow',
    bounds: document.getElementById("main")
};

class MainModal {

    constructor() {
        this.body = document.getElementById('main-modal');
        this.header = document.getElementById('main-modal-header');
        this.message = document.getElementById('main-modal-message');
        this.busy = document.getElementById('main-modal-busy');
        this.button = document.getElementById('main-modal-button');
    }

    show(header = null, message = null, button = false) {
        if (header) this.header.textContent = header;
        if (message) this.message.innerHTML = message;
        if (button) {
            this.busy.classList.add('hidden');
            this.button.classList.remove('hidden');
            this.button.addEventListener('click', () => this.confirm_button(this));
        } else {
            this.busy.classList.remove('hidden');
            this.button.classList.add('hidden');

        }
        this.body.classList.remove('hidden');
    }

    hide() {
        this.body.classList.add('hidden');
    }

    confirm_button(parent) {
        parent.hide()
        parent.button.removeEventListener('click', parent.confirm_button);
    }
}
const mainModal = new MainModal;

class Editor {
    constructor(option) {
        this.tags = [];
        this.categories = [];
        this.quill = new Quill('#editor-content', quillOptions);

        this.form = document.forms['editor'];

        this.pageTitle = document.getElementById('editor-page-title');

        this.option = option;
        this.set_editor(option);

        this.original;

        this.mainImageInput = document.getElementById('main-image-input');
        this.mainImageContainer = document.getElementById('main-image-preview-container');
        this.mainImagePreview = document.getElementById('main-image-preview');
    }

    set_editor(option, title = null, tags = null, category = null, mainImage = null, content = null, original = null) {
        this.option = option;
        let pageTitle;
        switch(option) {
            case 'new-post':
                pageTitle = 'New Page';
            break;
            case 'new-page':
                pageTitle = 'New Page';
            break;
            case 'edit-post':
                pageTitle = 'Edit Post';
            break;
            case 'edit-page':
                pageTitle = 'Edit Page';
            break;
        }

        this.original = original ? original : this.original;

        if (pageTitle) {
            this.set_page_title(pageTitle);
        }

        this.set_title(title);
        this.set_tags(tags);
        this.set_category(category);
        this.set_main_image(mainImage);
        this.set_content(content);

        this.build_editor();
    }

    async build_editor() {

        await this.get_category_tags();
        this.build_tag_buttons();
        this.build_category_dropdown();
    }

    async get_category_tags() {
        let promise = new Promise((resolve, reject) => {
            const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=get_data&page=editor`);

            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200){
                    const result = JSON.parse(this.responseText);
                    callback(result[0], result[1]);
                }
            }

            const callback = (tags, categories) => {
                this.tags = tags;
                this.categories = categories;
                resolve(true);
            }
        });

        return await promise;
    }

    build_tag_buttons() {
        const tags = this.tags.slice(0, 10);
        const tagsElement = document.getElementById('editor-tags');
        const tagsContainer = document.getElementById('editor-tags-container');
        tagsContainer.innerHTML = '';
        let fragment = document.createDocumentFragment();

        tags.forEach( (tag) => {
            tag = tag.replaceAll('-', ' ');
            const newTag = document.createElement('div');
            newTag.classList.add('add-tag');
            newTag.textContent = '+'+tag;
            fragment.appendChild(newTag);

            newTag.addEventListener('click', (e) => {
                if (tagsElement.value) {
                    tagsElement.value += ', ';
                }
                tagsElement.value += e.target.textContent.substring(1);
                tagsElement.focus();
            });
        });

        tagsContainer.appendChild(fragment);
    }

    build_category_dropdown() {
        const categories = this.categories;
        const fragment = document.createDocumentFragment();
        const categoryElement = document.getElementById('editor-category')
        const categoryDropdown = document.getElementById('category-dropdown');

        categoryElement.addEventListener('click', (e) => {
            e.stopPropagation();
            populate_category_dropdown();
            categoryElement.addEventListener('keyup', key_refresh);
            window.addEventListener('click', close);
        });

        const key_refresh = function(e) {
            populate_category_dropdown();
        }

        const close = function(e) {
            categoryDropdown.classList.add('hidden');
            categoryElement.removeEventListener('keyup', key_refresh);
            window.removeEventListener('click', close);
        }

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

    upload() {
        const form = this.form;
        if (!form['editor-title'].value) return false ;

        mainModal.show('Building your new post!', "This can take a minute. Please dont leave this page until it's done!");

        const fd = new FormData(form);
        fd.append('func', this.option);
        
        const content = editor.get_content();
        const imageElements = content.querySelectorAll('img');

        let i = 0;
        const images = {};
        imageElements.forEach(element => {
            images['image-'+i] = element.src;
            element.src = 'image-'+i;
            i++;
        });

        fd.append('images', JSON.stringify(images));
        fd.append('editor-content', content.innerHTML);
        fd.append('original', this.original);
        fd.append('remove-main-image', ((this.removeMainImage) ? 'true' : 'false'));

        const xhttp = new XMLHttpRequest();
                xhttp.open('POST', installUrl+'admin.php', true);
                xhttp.send(fd);

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200){
                const result = this.responseText;
                if (result.substr(0,7) === 'success') {
                    mainModal.show('Success!',"You're new post is live! You will be taken there in a few seconds.<br>Or click <a href=\""+homeUrl+result.substr(8)+"\">here</a> if you're not automatically redirected.");
                    setTimeout(function() {
                        location.href = homeUrl+result.substr(8);
                    }, 2000);
                } else if (result.includes('titleTaken')) {
                    mainModal.show('Title Already Used', 'That title (or one very similar) is already in use! Please try something different.', true)
                } else if (result.includes('imageError')) {
                    mainModal.show('Image Error', 'There was a problem uploading one of your images.<br> The server says: '+result, true);
                } else {
                    mainModal.show('Server Error', 'There was a problem with the server or your connection to it, please try again.');
                }
            }
        }

        i = 0;
        imageElements.forEach(element => {
            element.src = images['image-'+i];
            i++;
        });
    }

    set_page_title(text) {
        this.pageTitle.textContent = text;
    }

    set_title(text) {
        this.form['editor-title'].value = text;
    }

    set_tags(text) {
        this.form['editor-tags'].value = text;
    }

    set_category(text) {
        this.form['editor-category'].value = text;
    }

    set_main_image(path) {
        const overlay = document.getElementById('main-image-preview-overlay');

        if (path) {
            this.mainImageInput.classList.add('hidden');
            this.mainImageContainer.classList.remove('hidden');
            this.mainImagePreview.src = homeUrl+'images/'+path;

            document.getElementById('main-image-change').addEventListener('click', ()=> {
                this.form['editor-image'].click();
                this.form['editor-image'].addEventListener('change', () => {
                    overlay.classList.remove('hidden');
                    overlay.textContent = 'Changed!';
                });
            });

            document.getElementById('main-image-remove').addEventListener('click', () => {
                this.removeMainImage = true;
                overlay.classList.remove('hidden');
                overlay.textContent = 'Removed!';
            });
        } else {
            if(this.mainImageInput) {
                this.mainImageInput.classList.remove('hidden');
                this.mainImageContainer.classList.add('hidden');
            }
        }
    }

    set_content(text) {
        this.quill.root.innerHTML = text;
    }

    get_content() {
        return editor.quill.root;
    }
}
var editor = new Editor('new-post');

function change_page(page, subject = null) {   
    set_active_attributes(page);
    populate_active_page(page, subject);
}

function edit_post(post) {
    change_page('editor');
    const target = post.currentTarget.getAttribute('data-target');

    const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=get-post&post=${target}`);

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200){
                const r = (JSON.parse(this.responseText));
                editor.set_editor('edit-post', r['title'], r['tags'], r['category'], r['mainImage'], r['content'], r['urlTitle']);
            }
        }
}

function delete_post(post) {
    if (confirm('Are you sure you want to delete this? This CANNOT be undone!')) {
        const target = post.currentTarget.getAttribute('data-target');

        const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=delete-post&post=${target}`);

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200){
                change_page('posts');
            }
        }
    }
}

function set_active_attributes(page) {
    const items = [navbarItems, mainContents];
    items.forEach(item => {
        item.forEach(element => {
            if (element.getAttribute('data-page') == page || element.getAttribute('data-parent') == page) {
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
            let i = 0;
            result.forEach( (e)=> {
                const urlTitle = result[i]['urlTitle'];

                tr = document.createElement('tr');
                let first = true;
                for(const val in e) {
                    if (!buildTableIgnores.includes(val)){
                        const td = document.createElement('td');
                        td.setAttribute('data-postIndex', i);
                        td.textContent = e[val];

                        if (val == 'title') {
                            const link = document.createElement('a');
                            link.href = homeUrl+urlTitle;
                            link.textContent = td.textContent;
                            td.innerHTML = '';
                            td.appendChild(link);
                        }

                        tr.appendChild(td);
                    }
                }
                let td = document.createElement('td');
                td.appendChild(insert_icon('edit', urlTitle));
                td.firstChild.addEventListener('click', (target) => {
                    edit_post(target);
                });
                tr.appendChild(td);

                td = document.createElement('td');
                td.appendChild(insert_icon('delete', urlTitle));
                td.firstChild.addEventListener('click', (target) => {
                    delete_post(target);
                });
                tr.appendChild(td);
                fragment.appendChild(tr);
                i++;
            });
            tableBody.appendChild(fragment);
        }
        function insert_icon(icon, target = null) {
            const element = document.querySelector('.icon-'+icon).cloneNode(true);
            if (target) element.setAttribute('data-target', target);
            element.classList.remove('hidden');
            return element;
        }
    } else if (page == 'editor') {
        if (!editor) editor = new Editor();
        editor.set_editor('new-post');
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

function build_listners() {
    pageChangers.forEach(list => {
        list.forEach(element => {
            element.addEventListener('click', (event)=>{
                change_page(event.currentTarget.getAttribute('data-page'));
            });
        });
    });

    logoutButton.addEventListener('click', logout);

    editorButton.addEventListener('click', () => {
        editorSubmit.click()
        editor.upload();
    });
}

function init() {
    build_listners();
}

init();
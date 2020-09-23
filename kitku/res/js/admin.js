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

        this.parents;

        this.form = document.forms['editor'];

        this.pageTitle = document.getElementById('editor-page-title');

        this.option = option;
        this.set_editor(option);

        this.original;

        this.mainImageInput = document.getElementById('main-image-input');
        this.mainImageContainer = document.getElementById('main-image-preview-container');
        this.mainImagePreview = document.getElementById('main-image-preview');

        this.select = document.getElementById('editor-parent');
    }

    upload() {
        const form = this.form;
        const msg = this.option.split('-')[1];
        if (!form['editor-title'].value) return false ;

        mainModal.show('Building your '+msg+'!', "This can take a minute. Please dont leave this page until it's done!");

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
                    mainModal.show('Success!',"You're new "+msg+" is live! You will be taken there in a few seconds.<br>Or click <a href=\""+homeUrl+result.substr(8)+"\">here</a> if you're not automatically redirected.");
                    setTimeout(function() {
                        location.href = homeUrl+result.substr(8);
                    }, 2000);
                } else if (result.includes('titleTaken')) {
                    mainModal.show('Title Already Used', 'That title (or one very similar) is already in use! Please try something different.', true)
                } else if (result.includes('imageError')) {
                    mainModal.show('Image Error', 'There was a problem uploading one of your images.<br> The server says: '+result, true);
                } else if (result.includes('permissionDenied')) {
                    mainModal.show('Permission Denied', 'You may only change your own posts!', true);
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

    async build_editor(option) {

        if (option == 'new-post' || option == 'edit-post') {
            await this.get_category_tags();
            this.build_tag_buttons();
            this.build_category_dropdown();
        } else if(option == 'new-page' || option == 'edit-page') {
            await this.get_parents();
            this.build_parent_options();
        }
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

    build_parent_options() {
        this.select.innerHTML = '';
        const fragment = document.createDocumentFragment();
        const none = document.createElement('option');
        none.textContent = 'None';
        fragment.appendChild(none);
        this.parents.forEach((parent) => {
            const optionEle = document.createElement('option');
            if (parent != this.title) {
                optionEle.textContent = parent;
                fragment.appendChild(optionEle);
            }
        });
        this.select.appendChild(fragment);
    }

    set_editor(option, title = null, tags = null, category = null, mainImage = null, parent = null, blogPage = null, showInMenu = null, content = null, original = null) {
        if (!option) { return }
        this.option = option;
        this.title = title;
        let pageTitle;
        const formChildren = this.form.children;

        const split = option.split('-');

        if (split[1] == 'post') {
            for(let i=0; i<formChildren.length; i++) {
                if (formChildren[i].classList.contains('pages-hide')) {
                    formChildren[i].classList.remove('hidden');
                }
                if (formChildren[i].classList.contains('posts-hide')) {
                    formChildren[i].classList.add('hidden');
                }
            }
        } else {
            for(let i=0; i<formChildren.length; i++) {
                if (formChildren[i].classList.contains('pages-hide')) {
                    formChildren[i].classList.add('hidden');
                }
                if (formChildren[i].classList.contains('posts-hide')) {
                    formChildren[i].classList.remove('hidden');
                }
            }
        }

        pageTitle = split[0].charAt(0).toUpperCase()+split[0].slice(1) + ' ' + split[1].charAt(0).toUpperCase()+split[1].slice(1);

        this.original = original ? original : this.original;

        this.pageTitle.textContent = pageTitle;
        this.form['editor-title'].value = title;
        this.form['editor-tags'].value = tags;
        this.form['editor-category'].value = category;
        this.form['editor-parent'].value = parent
        this.form['editor-blog-page'].checked = (blogPage == '1') ? true : false; 
        this.form['editor-show-in-menu'].checked = (showInMenu == '1' || showInMenu === null) ? true : false;
        this.set_main_image(mainImage);
        this.quill.root.innerHTML = content;

        this.build_editor(option);
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

    async get_category_tags() {
        let promise = new Promise((resolve, reject) => {
            const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=get-data&page=editor&target=post`);

            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
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

    async get_parents() {
        let promise = new Promise((resolve, reject) => {
            const xhttp = new XMLHttpRequest();
                xhttp.open('POST', installUrl+'admin.php', true);
                xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhttp.send(`func=get-data&page=editor&target=page`);

            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    callback(JSON.parse(this.responseText));
                }
            }

            const callback = (parents) => {
                this.parents = parents;
                resolve(true);
            }
        });

        return await promise;
    }

    get_content() {
        return editor.quill.root;
    }
}
var editor = new Editor('new-post');

class Settings {
    constructor() {
        parent = this;
        document.querySelectorAll('.settings-submit').forEach((ele) => {
            ele.addEventListener('click', () => this.submit(ele));
        })
    }

    submit(ele) {
        const form = document.forms[ele.parentNode.name];
        switch (form['name']) {
            case 'password-change':
                if (!form['new-password'].value || !form['confirm-password'].value) {
                    return;
                }
            break;
            case 'email-change':
                if (!form['new-email'].value) {
                    return;
                }
            break;
            case 'user-perm':
                if (!form['user'].value || !form['power'].value) {
                    return;
                }
            break;
            case 'new-user':
                if (!form['user'].value || !form['power'].value || !form['email'] ||!form['password'].value || !form['confirm-password'].value) {
                    return;
                }
            break;
        }
        this.ajax_submit(form['name'], form);
    }

    ajax_submit(formName, formData) {
        const fd = new FormData(formData);
        fd.append('func', formName);

        const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php')
            xhttp.send(fd)

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                if (this.responseText == 'success') {
                    mainModal.show('Success!', ' ', true);
                } else {
                    mainModal.show('Failed', this.responseText, true);
                }
            }
        }
    }
}
var settings = new Settings();

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
            xhttp.send(`func=get-p&p=${target}`);

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200){
                const r = (JSON.parse(this.responseText));
                editor.set_editor('edit-'+r['table'], r['title'], r['tags'], r['category'], r['mainImage'], r['parent'], r['blogPage'], r['showInMenu'], r['content'], r['urlTitle']);
            }
        }
}

function delete_p(post) {
    if (confirm('Are you sure you want to delete this? This CANNOT be undone!')) {
        const target = post.currentTarget.getAttribute('data-target');

        const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=delete-p&target=${target}`);

        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200){
                if (this.responseText != 'success') {
                    mainModal.show('Permission Denied!', 'You may only delete your own posts', true);
                } else {
                    change_page('home');
                }
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

function populate_active_page(page, subject) {

    var result;

    if (page == 'posts' || page == 'pages') {

        const xhttp = new XMLHttpRequest();
            xhttp.open('POST', installUrl+'admin.php', true);
            xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhttp.send(`func=get-data&page=${page}&target=null`);

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
                    if (val == 'blogPage') {
                        th.textContent = 'Blog';
                    } else if (val == 'showInMenu') {
                        th.textContent = 'In Menu';
                    } else {
                        th.textContent = val.charAt(0).toUpperCase() + val.slice(1);
                    }
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
                for(const val in e) {
                    if (!buildTableIgnores.includes(val)){
                        const td = document.createElement('td');
                        td.setAttribute('data-postIndex', i);

                        if (val == 'title') {
                            const link = document.createElement('a');
                            link.href = homeUrl+urlTitle;
                            link.textContent = e[val];
                            td.classList.add('table-title');
                            td.innerHTML = '';
                            td.appendChild(link);
                        } else if (val == 'tags') {
                            const tags = e[val].replace(/,/g, ', ');
                            td.textContent = tags;
                        } else if (val == 'blogPage') {
                            td.textContent = (e[val] == 1 ? 'Blog' : '');  
                        } else if (val == 'showInMenu') {
                            td.textContent = (e[val] == 1 ? 'Yes' : 'No');  
                        } else {
                            td.textContent = e[val];
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
                    delete_p(target);
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
        editor.set_editor(subject);
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
                const target = event.currentTarget;
                change_page(target.getAttribute('data-page'), target.getAttribute('data-subject'));
            });
        });
    });

    logoutButton.addEventListener('click', logout);

    editorButton.addEventListener('click', () => {
        editorSubmit.click()
        editor.upload();
    });

    const close_nav = function() {
        document.getElementById('navbar').setAttribute('style', 'left: -100vw;')
        window.removeEventListener('click', close_nav);
    }
    document.getElementById('hamburger-menu').addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('navbar').setAttribute('style', 'left: 0;')
        window.addEventListener('click', close_nav);
    });
}

function init() {
    build_listners();
}

init();
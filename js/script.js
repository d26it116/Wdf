// ================= FUNCTION =================

function welcomeMessage() {

    console.log("Welcome to StudentHub!");

}

welcomeMessage();



// ================= DARK THEME =================

const themeBtn = document.getElementById("themeBtn");

if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-theme");
}

if (themeBtn) {
    themeBtn.addEventListener("click", function () {

        document.body.classList.toggle("dark-theme");

        if (document.body.classList.contains("dark-theme")) {
            localStorage.setItem("theme", "dark");
            themeBtn.textContent = "Light Theme";
        } else {
            localStorage.setItem("theme", "light");
            themeBtn.textContent = "Dark Theme";
        }

    });
}




// ================= CHANGE HEADING =================

const changeBtn = document.getElementById("changeBtn");

const welcomeHeading = document.getElementById("WelcomeHeading");


if (changeBtn && welcomeHeading) {

    changeBtn.addEventListener("click", function () {

        welcomeHeading.textContent =
            "Welcome to Our Student Portal";

    });

}



// ================= ANNOUNCEMENT MODAL =================

const openModalBtn = document.getElementById("openModalBtn");

const announcementModal =
    document.getElementById("announcementModal");

const closeModalBtn =
    document.getElementById("closeModalBtn");

const modalOkBtn =
    document.getElementById("modalOkBtn");


if (openModalBtn && announcementModal) {

    openModalBtn.addEventListener("click", function () {

        announcementModal.style.display = "flex";

    });

}


if (closeModalBtn && announcementModal) {

    closeModalBtn.addEventListener("click", function () {

        announcementModal.style.display = "none";

    });

}


if (modalOkBtn && announcementModal) {

    modalOkBtn.addEventListener("click", function () {

        announcementModal.style.display = "none";

    });

}



// ================= NOTIFICATION CLOSE =================

const closeBtn =
    document.getElementById("closebtn");

const notification =
    document.getElementById("notification");


if (closeBtn && notification) {

    closeBtn.addEventListener("click", function () {

        notification.style.display = "none";

    });

}



// ================= HAMBURGER MENU =================

const menuBtn =
    document.getElementById("menuBtn");

const mainNav =
    document.getElementById("mainNav");


function checkMenu() {

    if (window.innerWidth > 700) {

        // Desktop

        if (menuBtn) {
            menuBtn.style.display = "none";
        }

        if (mainNav) {
            mainNav.style.display = "block";
            mainNav.classList.remove("active");
        }

    } else {

        // Mobile

        if (menuBtn) {
            menuBtn.style.display = "block";
        }

        if (mainNav) {

            if (mainNav.classList.contains("active")) {

                mainNav.style.display = "block";

            } else {

                mainNav.style.display = "none";

            }

        }

    }

}


if (menuBtn && mainNav) {

    menuBtn.addEventListener("click", function () {

        mainNav.classList.toggle("active");

        if (mainNav.classList.contains("active")) {

            mainNav.style.display = "block";

        } else {

            mainNav.style.display = "none";

        }

    });

    checkMenu();

    window.addEventListener("resize", checkMenu);

}

// ================= IMAGE SLIDER =================

let currentImage = 0;


const images = [

    "../images/Campus1.webp",

    "../images/Campus2.webp",

    "../images/Campus3.jpg"

];


function showImage() {

    const aboutImage =
        document.getElementById("aboutImage");

    if (aboutImage) {

        aboutImage.src = images[currentImage];

    }

}


function nextImage() {

    currentImage++;

    if (currentImage >= images.length) {

        currentImage = 0;

    }

    showImage();

}


function previousImage() {

    currentImage--;

    if (currentImage < 0) {

        currentImage = images.length - 1;

    }

    showImage();

}



const form =
    document.getElementById("registerForm");


if (form) {

    form.addEventListener("submit", function (event) {

        event.preventDefault();

        let name =
            document.getElementById("name").value.trim();

        let email =
            document.getElementById("email").value.trim();

        let mobile =
            document.getElementById("mobile").value.trim();

        let password =
            document.getElementById("password").value;

        let confirmPassword =
            document.getElementById("confirmPassword").value;

        let course =
            document.getElementById("course").value;

        let year =
            document.getElementById("year").value;


        let gender =
            document.querySelector(
                'input[name="gender"]:checked'
            );


        let terms =
            document.getElementById("terms").checked;


        let namePattern =
            /^[A-Za-z ]+$/;


        let emailPattern =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


        let mobilePattern =
            /^[0-9]{10}$/;


        let passwordPattern =
            /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;


        document.querySelectorAll(".error").forEach(function (error) {

            error.textContent = "";

        });


        document.getElementById("successMessage").textContent = "";

        if (name === "") {

            document.getElementById("nameError").textContent =
                "Name is required.";

        }

        else if (!namePattern.test(name)) {

            document.getElementById("nameError").textContent =
                "Name should contain only letters.";

        }


        if (email === "") {

            document.getElementById("emailError").textContent =
                "Email is required.";

        }

        else if (!emailPattern.test(email)) {

            document.getElementById("emailError").textContent =
                "Enter a valid email.";

        }


        if (mobile === "") {

            document.getElementById("mobileError").textContent =
                "Mobile number is required.";

        }

        else if (!mobilePattern.test(mobile)) {

            document.getElementById("mobileError").textContent =
                "Mobile number must contain 10 digits.";

        }


        if (password === "") {

            document.getElementById("passwordError").textContent =
                "Password is required.";

        }

        else if (!passwordPattern.test(password)) {

            document.getElementById("passwordError").textContent =
                "Password must have 6 characters, one letter and one number.";

        }


        if (confirmPassword === "") {

            document.getElementById("confirmPasswordError").textContent =
                "Please confirm your password.";

        }

        else if (password !== confirmPassword) {

            document.getElementById("confirmPasswordError").textContent =
                "Passwords do not match.";

        }

        if (course === "") {

            document.getElementById("courseError").textContent =
                "Please select a course.";

        }


        if (year === "") {

            document.getElementById("yearError").textContent =
                "Please enter your year.";

        }

        else if (year < 1 || year > 4) {

            document.getElementById("yearError").textContent =
                "Year must be between 1 and 4.";

        }


        if (!gender) {

            document.getElementById("genderError").textContent =
                "Please select your gender.";

        }

        if (!terms) {

            document.getElementById("termsError").textContent =
                "Please accept the terms and conditions.";

        }

        let errors =
            document.querySelectorAll(".error");

        let hasError = false;


        errors.forEach(function (error) {

            if (error.textContent !== "") {

                hasError = true;

            }

        });


        if (!hasError) {

            document.getElementById("successMessage").textContent =
                "Registration Successful!!!!!!!!";

        }

    });

    form.addEventListener("reset", function () {

        document.querySelectorAll(".error").forEach(function (error) {

            error.textContent = "";

        });


        document.getElementById("successMessage").textContent = "";

    });

}

// ================= FAQ =================

let faqs = [];

const faqBox = document.getElementById("faqContainer");

if (faqBox) {

    fetch("../data/faqs.json")
        .then(response => response.json())
        .then(data => {

            faqs = data;
            displayFAQs(faqs);

        });


    function displayFAQs(data) {

        faqBox.innerHTML = "";

        data.forEach(faq => {

            faqBox.innerHTML += `
                <div class="faq-card">

                    <h3>${faq.question}</h3>

                    <p>${faq.answer}</p>

                </div>
            `;

        });

    }


    const searchBox =
        document.getElementById("searchBox");

    if (searchBox) {

        searchBox.addEventListener("input", function() {

            let text = this.value.toLowerCase();

            let result = faqs.filter(faq =>
                faq.question.toLowerCase().includes(text)
            );

            displayFAQs(result);

        });

    }


    const filterBox =
        document.getElementById("filterBox");

    if (filterBox) {

        filterBox.addEventListener("change", function() {

            let value = this.value;

            if (value === "all") {

                displayFAQs(faqs);

                return;

            }

            let result = faqs.filter(faq =>
                faq.question.toLowerCase().includes(value)
            );

            displayFAQs(result);

        });

    }

}



// ================= EVENTS =================

let events = [];

let currentPage = 1;

let perPage = 5;

let totalPages = 1;


const eventBox =
    document.getElementById("eventContainer");


if (eventBox) {


    // Load JSON

    fetch("../data/event.json")

        .then(response => response.json())

        .then(data => {

            events = data;

            document.getElementById("loading")
                .style.display = "none";

            displayEvents();

        });


    // Display Events

    function displayEvents() {

        let search =
            document.getElementById("searchEvent")
                .value.toLowerCase();

        let category =
            document.getElementById("category")
                .value;

        let sort =
            document.getElementById("sort")
                .value;


        // Search + Category

        let result = events.filter(event =>

            event.name.toLowerCase().includes(search) &&

            (category === "all" ||
             event.category === category)

        );


        // Sort

        if (sort === "az") {

            result.sort((a, b) =>
                a.name.localeCompare(b.name)
            );

        }

        if (sort === "za") {

            result.sort((a, b) =>
                b.name.localeCompare(a.name)
            );

        }


        // Pagination

        totalPages =
            Math.ceil(result.length / perPage) || 1;

        if (currentPage > totalPages) {

            currentPage = totalPages;

        }


        let start =
            (currentPage - 1) * perPage;

        let data =
            result.slice(start, start + perPage);


        // Display

        eventBox.innerHTML = "";


        if (data.length === 0) {

            eventBox.innerHTML =
                "<p>No events found.</p>";

        }


        data.forEach(event => {

            eventBox.innerHTML += `

                <article class="event-card">

                    <h3>${event.name}</h3>

                    <p>
                        <strong>Category:</strong>
                        ${event.category}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        ${event.date}
                    </p>

                    <p>
                        <strong>Location:</strong>
                        ${event.location}
                    </p>

                    <button type="button">
                        View Event
                    </button>

                </article>

            `;

        });


        // Page

        document.getElementById("page").textContent =
            "Page " + currentPage +
            " of " + totalPages;


        document.getElementById("prev").disabled =
            currentPage === 1;

        document.getElementById("next").disabled =
            currentPage === totalPages;

    }


    // Search

    document.getElementById("searchEvent")
        .addEventListener("input", function() {

            currentPage = 1;

            displayEvents();

        });


    // Category

    document.getElementById("category")
        .addEventListener("change", function() {

            currentPage = 1;

            displayEvents();

        });


    // Sort

    document.getElementById("sort")
        .addEventListener("change", function() {

            currentPage = 1;

            displayEvents();

        });


    // Previous

    document.getElementById("prev")
        .addEventListener("click", function() {

            if (currentPage > 1) {

                currentPage--;

                displayEvents();

            }

        });


    // Next

    document.getElementById("next")
        .addEventListener("click", function() {

            if (currentPage < totalPages) {

                currentPage++;

                displayEvents();

            }

        });

}


// ================= STUDENTS =================

let students = [];

let studentPage = 1;

let studentPerPage = 5;

const studentBox =
    document.getElementById("studentContainer");


if (studentBox) {

    fetch("../Data/student.json")

        .then(response => response.json())

        .then(data => {

            students = data;

            document.getElementById("studentLoading")
                .style.display = "none";

            showStudents();

        });


    function showStudents() {

        let search =
            document.getElementById("studentSearch")
            .value.toLowerCase();

        let category =
            document.getElementById("studentCategory")
            .value;

        let sort =
            document.getElementById("studentSort")
            .value;


        // Search and filter

        let result = students.filter(student =>

            student.name.toLowerCase().includes(search) &&

            (category === "all" ||
             student.course === category)

        );


        // Sort A-Z

        if (sort === "az") {

            result.sort((a, b) =>
                a.name.localeCompare(b.name)
            );

        }


        // Sort Z-A

        if (sort === "za") {

            result.sort((a, b) =>
                b.name.localeCompare(a.name)
            );

        }


        // Total pages

        let pages =
            Math.ceil(result.length / studentPerPage) || 1;


        // Prevent invalid page

        if (studentPage > pages) {

            studentPage = pages;

        }


        // Get 5 students

        let start =
            (studentPage - 1) * studentPerPage;

        let data =
            result.slice(start, start + studentPerPage);


        // Clear old cards

        studentBox.innerHTML = "";


        // Display students

        data.forEach(student => {

            studentBox.innerHTML += `

                <article class="student-card">

                    <h3>${student.name}</h3>

                    <p>
                        <strong>Course:</strong>
                        ${student.course}
                    </p>

                    <p>
                        <strong>Year:</strong>
                        ${student.year}
                    </p>

                    <p>
                        <strong>Email:</strong>
                        ${student.email}
                    </p>

                    <button type="button">
                        View Student
                    </button>

                </article>

            `;

        });


        // No students

        if (data.length === 0) {

            studentBox.innerHTML =
                "<p>No students found.</p>";

        }


        // Page number

        document.getElementById("studentPage")
            .textContent =
            `Page ${studentPage} of ${pages}`;


        // Previous button

        document.getElementById("studentPrev")
            .disabled =
            studentPage === 1;


        // Next button

        document.getElementById("studentNext")
            .disabled =
            studentPage === pages;

    }


    // Search

    document.getElementById("studentSearch")
        .oninput = () => {

            studentPage = 1;

            showStudents();

        };


    // Category filter

    document.getElementById("studentCategory")
        .onchange = () => {

            studentPage = 1;

            showStudents();

        };


    // Sort

    document.getElementById("studentSort")
        .onchange = () => {

            studentPage = 1;

            showStudents();

        };


    // Previous

    document.getElementById("studentPrev")
        .onclick = () => {

            if (studentPage > 1) {

                studentPage--;

                showStudents();

            }

        };


    // Next

    document.getElementById("studentNext")
        .onclick = () => {

            studentPage++;

            showStudents();

        };

}


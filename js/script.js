


// ================= FUNCTION =================

function welcomeMessage() {
    console.log("Welcome to StudentHub!");
}

welcomeMessage();


// ================= DARK THEME =================

const themeBtn = document.getElementById("themeBtn");

// Apply saved theme
if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-theme");
}

// Update button text
if (themeBtn) {
    themeBtn.textContent =
        document.body.classList.contains("dark-theme")
            ? "Light Theme"
            : "Dark Theme";

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
const announcementModal = document.getElementById("announcementModal");
const closeModalBtn = document.getElementById("closeModalBtn");
const modalOkBtn = document.getElementById("modalOkBtn");

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

const closeBtn = document.getElementById("closebtn");
const notification = document.getElementById("notification");

if (closeBtn && notification) {

    closeBtn.addEventListener("click", function () {
        notification.style.display = "none";
    });

}


// ================= HAMBURGER MENU =================

const menuBtn = document.getElementById("menuBtn");
const mainNav = document.getElementById("mainNav");

function checkMenu() {
    if (window.innerWidth > 700) {
        // Desktop
        menuBtn.style.display = "none";
        mainNav.style.display = "block";
        mainNav.classList.remove("active");
    } else {
        // Mobile
        menuBtn.style.display = "block";

        if (mainNav.classList.contains("active")) {
            mainNav.style.display = "block";
        } else {
            mainNav.style.display = "none";
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

    // Check when page loads
    checkMenu();

    // Check when screen size changes
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

    const aboutImage = document.getElementById("aboutImage");

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
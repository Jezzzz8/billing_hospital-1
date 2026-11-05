(() => {
    const body = document.body;
    const pagesIndex = location.pathname.lastIndexOf("/pages/");
    const baseUrl = body.dataset.baseUrl
        || (pagesIndex >= 0
            ? location.pathname.slice(0, pagesIndex)
            : location.pathname.slice(0, location.pathname.lastIndexOf("/")));
    const backend = body.dataset.routeBackend
        ? `${baseUrl}/${body.dataset.routeBackend.replace(/^\/+/, "")}`
        : `${baseUrl}/api/router.php`;
    const route = location.pathname.split("/").pop() || "index.html";
    const page = body.dataset.routePage || route.replace(/.html$/, "");
    const target = new URL(backend, location.origin);

    if (page && page !== "index") {
        target.searchParams.set("page", page);
    }
    if (target.pathname.endsWith("/api/router.php")) {
        target.searchParams.set("shell", "1");
    }
    for (const [key, value] of new URLSearchParams(location.search)) {
        target.searchParams.set(key, value);
    }

    fetch(target, { credentials: "same-origin" })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Could not load this page (${response.status}).`);
            }
            return response.text();
        })
        .then(html => {
            document.open();
            document.write(html);
            document.close();
        })
        .catch(error => {
            console.error("Could not load the requested page.", error);
            document.body.replaceChildren(
                Object.assign(document.createElement("p"), {
                    textContent: "This page could not be loaded. Please try again."
                })
            );
        });
})();

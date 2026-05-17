// resources/js/config/menu.js

import {
    Send,
    Settings,
} from "lucide-vue-next";

export const menu = [
    {
        label: "Transfers",
        icon: Send,
        to: "/transfers",
    },
    {
        label: "Settings",
        icon: Settings,
        to: "/settings",
    },
];

import {
    ArrowLeftRight,
    Settings,
    ShieldCheck,
} from "lucide-vue-next";

export const menu = [
    {
        label: "Transfers",
        icon: ArrowLeftRight,
        to: "/transfers",
    },
    {
        label: "Users",
        icon: ShieldCheck,
        to: "/users",
    },
    {
        label: "Settings",
        icon: Settings,
        to: "/settings",
    },
];

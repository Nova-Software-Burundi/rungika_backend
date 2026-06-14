import {
    ArrowLeftRight,
    CircleDollarSign,
    LifeBuoy,
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
        label: "Currencies",
        icon: CircleDollarSign,
        to: "/currencies",
    },
    {
        label: "Support",
        icon: LifeBuoy,
        to: "/support",
    },
    {
        label: "Settings",
        icon: Settings,
        to: "/settings",
    },
];

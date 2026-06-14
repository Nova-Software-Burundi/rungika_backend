import {
    ArrowLeftRight,
    CircleDollarSign,
    LifeBuoy,
    Settings,
    ShieldCheck,
    Coins,
    CreditCard,
    Megaphone,
} from "lucide-vue-next";

export const menu = [
    { label: "Transfers", icon: ArrowLeftRight, to: "/transfers" },
    { label: "Users", icon: ShieldCheck, to: "/users" },
    { label: "Currencies", icon: CircleDollarSign, to: "/currencies" },
    { label: "Assets", icon: Coins, to: "/assets" },
    { label: "Payment Methods", icon: CreditCard, to: "/payment-methods" },
    { label: "Ads", icon: Megaphone, to: "/ads" },
    { label: "Support", icon: LifeBuoy, to: "/support" },
    { label: "Settings", icon: Settings, to: "/settings" },
];

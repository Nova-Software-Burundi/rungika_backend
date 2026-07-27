import {
    LayoutDashboard,
    Banknote,
    LifeBuoy,
    ShieldCheck,
    UserCheck,
    Star,
    BarChart3,
    FileText,
    Globe,
    CircleDollarSign,
    CreditCard,
    Megaphone,
    Settings,
    Coins,
    ArrowLeftRight,
    TrendingUp,
} from "lucide-vue-next";

export const menu = [
    {
        section: "Operations",
        items: [
            { label: "Dashboard", icon: LayoutDashboard, to: "/dashboard" },
            { label: "Remittances", icon: Banknote, to: "/remittances" },
            { label: "Support", icon: LifeBuoy, to: "/support" },
        ],
    },
    {
        section: "People",
        items: [
            { label: "Users", icon: ShieldCheck, to: "/users" },
            { label: "Ratings", icon: Star, to: "/ratings" },
        ],
    },
    {
        section: "Finance",
        items: [
            { label: "Reports", icon: FileText, to: "/reports" },
            { label: "Revenue", icon: BarChart3, to: "/revenue" },
        ],
    },
    {
        section: "Configuration",
        items: [
            { label: "Countries", icon: Globe, to: "/countries" },
            { label: "Currencies", icon: CircleDollarSign, to: "/currencies" },
            { label: "Payment Methods", icon: CreditCard, to: "/payment-methods" },
            { label: "Announcements", icon: Megaphone, to: "/announcements" },
        ],
    },
    {
        section: "Legacy",
        items: [
            { label: "Ads", icon: Megaphone, to: "/ads" },
            { label: "Trades", icon: ArrowLeftRight, to: "/trades" },
            { label: "Assets", icon: Coins, to: "/assets" },
            { label: "Fees", icon: Coins, to: "/platform-fees" },
            { label: "Prices", icon: TrendingUp, to: "/reference-prices" },
        ],
    },
];

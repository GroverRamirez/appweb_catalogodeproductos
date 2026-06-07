import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 text-xs font-semibold w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "border-border bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
         "border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        success:
          "border-emerald-700/15 bg-emerald-700 text-white [a&]:hover:bg-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-500/20 dark:text-emerald-200",
        warn:
          "border-amber-700/20 bg-amber-600 text-white [a&]:hover:bg-amber-700 dark:border-amber-400/30 dark:bg-amber-500/20 dark:text-amber-200",
        danger:
          "border-red-700/15 bg-red-700 text-white [a&]:hover:bg-red-800 dark:border-red-400/30 dark:bg-red-500/20 dark:text-red-200",
        info:
          "border-sky-700/15 bg-sky-700 text-white [a&]:hover:bg-sky-800 dark:border-sky-400/30 dark:bg-sky-500/20 dark:text-sky-200",
        outline:
          "border-input bg-card text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>

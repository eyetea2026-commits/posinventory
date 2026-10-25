{{-- Bigger base text size for the admin pages (included by admin/layout
     only; the cashier side and the login pages keep the browser default).
     Almost all text is sized in rem, so the root size sets it everywhere at
     once. Fluid: 16px on phones growing to 18px (112.5%) from about 980px
     wide, so phones aren't crowded and big screens keep the larger text.
     Percentages (not px) keep the viewer's own browser font setting in play. --}}
<style>html { font-size: clamp(100%, 85% + 0.45vw, 112.5%); }</style>

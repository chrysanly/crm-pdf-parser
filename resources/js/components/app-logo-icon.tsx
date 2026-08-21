import type { SVGAttributes } from 'react';

/**
 * The CRM PDF Parser mark: a document whose lines are being read out of it.
 * Single-colour and hole-based (fill-rule evenodd) so it inherits
 * `fill-current` wherever it is placed — sidebar, auth card, print header.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M7 1H21.4142L30 9.58579V33C30 34.6569 28.6569 36 27 36H7C5.34315 36 4 34.6569 4 33V4C4 2.34315 5.34315 1 7 1ZM8 5V32H26V13H19V5H8ZM22 5.82843L25.1716 9H22V5.82843ZM10 16H24V19H10V16ZM10 22H24V25H10V22ZM10 28H19V31H10V28Z"
            />
            <path d="M31.5 24H38L34.75 30.5L31.5 24Z" />
            <path d="M33 15H36.5V23H33V15Z" />
        </svg>
    );
}

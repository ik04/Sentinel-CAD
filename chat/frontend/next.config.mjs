/** @type {import('next').NextConfig} */
const nextConfig = {
    reactStrictMode: true,
    redirects: async () => {
        return [
            {
                source: '/',
                destination: '/login',
                permanent: false,
            },
        ]
    },
    images: {
        domains: [
            'avatar.iran.liara.run'
        ]
    }
};

export default nextConfig;